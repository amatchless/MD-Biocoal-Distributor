<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealerRequest;
use App\Models\Dealer;
use App\Models\DealerDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['q', 'status', 'type', 'employee', 'from', 'to']);

        $dealers = Dealer::visibleTo($user)
            ->filter($filters)
            ->with('creator:id,name')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $employees = $user->isEmployee() ? collect() : $this->teamFor($user);

        return view('dealers.index', compact('dealers', 'filters', 'employees'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('dealers.form', ['dealer' => new Dealer([
            'partner_type' => 'dealer',
            'application_type' => 'new',
            'financial_year' => Dealer::currentFinancialYear(),
            'gst_registered' => true,
            'agreement_date' => now(),
            'manager_name' => $user->manager?->name,
        ])]);
    }

    public function store(DealerRequest $request): RedirectResponse|JsonResponse
    {
        $dealer = DB::transaction(function () use ($request) {
            $dealer = new Dealer($request->dealerAttributes());
            $dealer->created_by = $request->user()->id;
            $dealer->status = 'submitted';
            $dealer->consent_given = true;
            $dealer->consent_at = now();
            $dealer->terms_version = Dealer::TERMS_VERSIONS[$dealer->partner_type];
            $dealer->terms_accepted_at = now();
            $dealer->user_agent = substr((string) $request->userAgent(), 0, 300);
            $dealer->ip_address = $request->ip();
            $dealer->save();

            $this->storeDocuments($request, $dealer);

            $dealer->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'submitted',
                'meta' => [
                    'latitude' => $dealer->latitude,
                    'longitude' => $dealer->longitude,
                    'accuracy' => $dealer->geo_accuracy,
                ],
            ]);

            return $dealer;
        });

        return $this->saved($request, $dealer, "{$dealer->typeLabel()} onboarded. Reference {$dealer->ref_no}.");
    }

    public function show(Request $request, Dealer $dealer): View
    {
        Gate::authorize('view', $dealer);
        $dealer->load(['creator.manager', 'creator.position', 'reviewer', 'documents', 'activities.user']);

        $duplicates = Dealer::query()
            ->whereKeyNot($dealer->id)
            ->where(function ($q) use ($dealer) {
                $q->where('pan_no', $dealer->pan_no);
                if ($dealer->gst_no) {
                    $q->orWhere('gst_no', $dealer->gst_no);
                }
            })
            ->with('creator:id,name')
            ->get();

        return view('dealers.show', compact('dealer', 'duplicates'));
    }

    public function edit(Dealer $dealer): View
    {
        Gate::authorize('update', $dealer);

        return view('dealers.form', compact('dealer'));
    }

    public function update(DealerRequest $request, Dealer $dealer): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $dealer);

        DB::transaction(function () use ($request, $dealer) {
            $attributes = $request->dealerAttributes();
            if (blank($attributes['bank_account_no'] ?? null)) {
                unset($attributes['bank_account_no']); // blank means "keep the saved number"
            }
            $dealer->fill($attributes);

            $resubmitted = $dealer->status === 'needs_info';
            if ($resubmitted) {
                $dealer->status = 'submitted';
            }
            $dealer->save();

            $this->storeDocuments($request, $dealer);

            $dealer->activities()->create([
                'user_id' => $request->user()->id,
                'action' => $resubmitted ? 'resubmitted' : 'edited',
            ]);
        });

        return $this->saved($request, $dealer, 'Changes saved.');
    }

    public function review(Request $request, Dealer $dealer): RedirectResponse
    {
        Gate::authorize('review', $dealer);

        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'needs_info', 'approved', 'rejected'])],
            'remarks' => ['nullable', 'required_if:status,needs_info,rejected', 'string', 'max:2000'],
        ], ['remarks.required_if' => 'Add remarks explaining this decision.']);

        $dealer->status = $data['status'];
        $dealer->review_remarks = $data['remarks'] ?? null;
        $dealer->reviewed_by = $request->user()->id;
        $dealer->reviewed_at = now();
        $dealer->save();

        $dealer->activities()->create([
            'user_id' => $request->user()->id,
            'action' => $data['status'],
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back()->with('status', "Marked as {$dealer->statusLabel()}.");
    }

    public function addDocuments(Request $request, Dealer $dealer): RedirectResponse
    {
        Gate::authorize('addDocuments', $dealer);

        $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
            'documents.*.category' => ['required', Rule::in(array_keys(DealerDocument::CATEGORIES))],
            'documents.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'documents.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'documents.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'documents.*.captured_at' => ['nullable', 'date'],
        ]);

        $count = $this->storeDocuments($request, $dealer);
        $dealer->activities()->create(['user_id' => $request->user()->id, 'action' => 'documents_added', 'meta' => ['count' => $count]]);

        return back()->with('status', "{$count} file(s) added.");
    }

    public function document(Dealer $dealer, DealerDocument $document): StreamedResponse
    {
        Gate::authorize('view', $dealer);
        abort_unless($document->dealer_id === $dealer->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Warns the employee in the form if the GSTIN or PAN was already onboarded by anyone. */
    public function duplicateCheck(Request $request): JsonResponse
    {
        $gst = strtoupper(trim((string) $request->query('gst')));
        $pan = strtoupper(trim((string) $request->query('pan')));
        if ($gst === '' && $pan === '') {
            return response()->json(['matches' => []]);
        }

        $matches = Dealer::query()
            ->when($request->integer('exclude'), fn ($q, $id) => $q->whereKeyNot($id))
            ->where(function ($q) use ($gst, $pan) {
                if ($gst !== '') {
                    $q->orWhere('gst_no', $gst);
                }
                if ($pan !== '') {
                    $q->orWhere('pan_no', $pan);
                }
            })
            ->with('creator:id,name')
            ->limit(5)
            ->get()
            ->map(fn (Dealer $d) => [
                'ref_no' => $d->ref_no,
                'business_name' => $d->business_name,
                'status' => $d->statusLabel(),
                'onboarded_by' => $d->creator?->name,
                'matched_on' => $gst !== '' && $d->gst_no === $gst ? 'GSTIN' : 'PAN',
            ]);

        return response()->json(['matches' => $matches]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Dealer::visibleTo($request->user())
            ->filter($request->only(['q', 'status', 'type', 'employee', 'from', 'to']))
            ->with('creator.manager')
            ->withCount('documents')
            ->latest();

        $columns = [
            'Ref No' => fn (Dealer $d) => $d->ref_no,
            'Type' => fn (Dealer $d) => $d->typeLabel(),
            'New / renewal' => fn (Dealer $d) => $d->applicationTypeLabel(),
            'Financial year' => fn (Dealer $d) => $d->financial_year,
            'Business name' => fn (Dealer $d) => $d->business_name,
            'Constitution' => fn (Dealer $d) => $d->entity_type,
            'Contact person' => fn (Dealer $d) => $d->contact_name,
            'Mobile' => fn (Dealer $d) => $d->contact_phone,
            'Email' => fn (Dealer $d) => $d->email,
            'State head' => fn (Dealer $d) => $d->state_head,
            'Address' => fn (Dealer $d) => trim($d->address_line1.' '.$d->address_line2),
            'Town / village' => fn (Dealer $d) => $d->city,
            'Tehsil' => fn (Dealer $d) => $d->tehsil,
            'District' => fn (Dealer $d) => $d->district,
            'State' => fn (Dealer $d) => $d->state,
            'PIN' => fn (Dealer $d) => $d->pincode,
            'GSTIN' => fn (Dealer $d) => $d->gst_no,
            'PAN' => fn (Dealer $d) => $d->pan_no,
            'Pesticide licence' => fn (Dealer $d) => $d->pesticide_licence_no,
            'Pesticide expiry' => fn (Dealer $d) => $d->pesticide_licence_expiry?->format('Y-m-d'),
            'Fertilizer licence' => fn (Dealer $d) => $d->fertilizer_licence_no,
            'Fertilizer expiry' => fn (Dealer $d) => $d->fertilizer_licence_expiry?->format('Y-m-d'),
            'Security deposit' => fn (Dealer $d) => $d->security_deposit_amount,
            'Deposit mode / ref' => fn (Dealer $d) => trim($d->security_deposit_mode.' '.$d->security_deposit_ref),
            'Deposit date' => fn (Dealer $d) => $d->security_deposit_date?->format('Y-m-d'),
            'Cheque bank' => fn (Dealer $d) => trim($d->bank_name.' '.$d->bank_branch),
            'Cheque nos.' => fn (Dealer $d) => implode(', ', array_filter([$d->cheque_1_no, $d->cheque_2_no])),
            'Cash discount slab' => fn (Dealer $d) => $d->cashDiscountLabel(),
            'Terms accepted' => fn (Dealer $d) => $d->terms_version,
            'Signatory' => fn (Dealer $d) => trim($d->signatory_name.' ('.$d->signatory_designation.')', ' ()'),
            'Emp. code' => fn (Dealer $d) => $d->employee_code,
            'Appointing manager' => fn (Dealer $d) => trim($d->appointing_manager_name.' '.$d->appointing_manager_code),
            'Reporting head' => fn (Dealer $d) => $d->reporting_head_name,
            'Latitude' => fn (Dealer $d) => $d->latitude,
            'Longitude' => fn (Dealer $d) => $d->longitude,
            'GPS accuracy (m)' => fn (Dealer $d) => $d->geo_accuracy !== null ? round($d->geo_accuracy) : null,
            'Documents' => fn (Dealer $d) => $d->documents_count,
            'Status' => fn (Dealer $d) => $d->statusLabel(),
            'Onboarded by' => fn (Dealer $d) => $d->creator?->name,
            'Manager' => fn (Dealer $d) => $d->creator?->manager?->name,
            'Onboarded at' => fn (Dealer $d) => $d->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            'Review remarks' => fn (Dealer $d) => $d->review_remarks,
        ];

        $filename = 'md-biocoals-onboarding-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys($columns));
            $query->chunk(500, function ($dealers) use ($out, $columns) {
                foreach ($dealers as $dealer) {
                    // Prefix values that a spreadsheet would treat as a formula.
                    fputcsv($out, array_map(function ($fn) use ($dealer) {
                        $v = (string) $fn($dealer);

                        return preg_match('/^[=+\-@]/', $v) ? "'".$v : $v;
                    }, $columns));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The form posts with XHR (for upload progress), so answer with where to go next. */
    private function saved(Request $request, Dealer $dealer, string $message): RedirectResponse|JsonResponse
    {
        $url = route('dealers.show', $dealer);
        session()->flash('status', $message);
        session()->flash('clear_draft', true);

        return $request->wantsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }

    private function storeDocuments(Request $request, Dealer $dealer): int
    {
        $count = 0;
        foreach ($request->input('documents', []) as $i => $meta) {
            $file = $request->file("documents.$i.file");
            if (! $file) {
                continue;
            }
            $dealer->documents()->create([
                'category' => $meta['category'],
                'original_name' => substr($file->getClientOriginalName(), 0, 200),
                'path' => $file->store("dealers/{$dealer->id}", 'local'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'latitude' => $meta['latitude'] ?? null,
                'longitude' => $meta['longitude'] ?? null,
                'geo_accuracy' => $meta['accuracy'] ?? null,
                'captured_at' => $meta['captured_at'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
            $count++;
        }

        return $count;
    }

    private function teamFor(User $user)
    {
        return $user->isSuperAdmin()
            ? User::role([User::EMPLOYEE, User::MANAGER])->orderBy('name')->get(['id', 'name'])
            : $user->teamMembers()->orderBy('name')->get(['id', 'name'])->prepend($user);
    }
}
