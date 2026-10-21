<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeOnboarding;
use App\Models\OnboardingCheck;
use App\Models\OnboardingDocument;
use App\Models\OnboardingReference;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Employee onboarding and background verification, run by the HR department. */
class EmployeeOnboardingController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmployeeOnboarding::class);
        $filters = $request->only(['q', 'status', 'department', 'owner', 'overdue']);

        $onboardings = EmployeeOnboarding::filter($filters)
            ->with(['department:id,name', 'position', 'owner:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = EmployeeOnboarding::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $overdue = EmployeeOnboarding::filter(['overdue' => true])->count();

        return view('onboarding.index', [
            'onboardings' => $onboardings,
            'filters' => $filters,
            'counts' => $counts,
            'overdue' => $overdue,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'owners' => $this->hrUsers(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', EmployeeOnboarding::class);

        return view('onboarding.form', $this->formData(new EmployeeOnboarding([
            'owner_id' => $request->user()->id,
            'target_date' => now()->addDays(14),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', EmployeeOnboarding::class);
        $data = $this->validatedCase($request);

        [$onboarding, $token] = DB::transaction(function () use ($data, $request) {
            $onboarding = new EmployeeOnboarding($data);
            $onboarding->created_by = $request->user()->id;
            $onboarding->status = 'invited';
            $onboarding->save();
            $token = $onboarding->issueAccessToken();
            $onboarding->activities()->create(['user_id' => $request->user()->id, 'action' => 'created']);

            return [$onboarding, $token];
        });

        return redirect()->route('onboarding.show', $onboarding)
            ->with('status', "Case {$onboarding->ref_no} opened. Share the link below with {$onboarding->candidate_name}.")
            ->with('candidate_link', route('candidate.show', $token));
    }

    public function show(EmployeeOnboarding $onboarding): View
    {
        Gate::authorize('view', $onboarding);
        $onboarding->load([
            'department', 'position', 'manager', 'owner', 'creator', 'assessor', 'decider', 'user',
            'documents.uploader', 'checks.checker', 'references.logger', 'activities.user',
        ]);

        return view('onboarding.show', ['onboarding' => $onboarding]);
    }

    public function edit(EmployeeOnboarding $onboarding): View
    {
        Gate::authorize('update', $onboarding);

        return view('onboarding.form', $this->formData($onboarding));
    }

    public function update(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        $onboarding->update($this->validatedCase($request));
        $onboarding->activities()->create(['user_id' => $request->user()->id, 'action' => 'details_updated']);

        return redirect()->route('onboarding.show', $onboarding)->with('status', 'Case details saved.');
    }

    /** A new link for the candidate. The old one stops working. */
    public function issueLink(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        abort_unless(in_array($onboarding->status, EmployeeOnboarding::CANDIDATE_EDITABLE, true), 422, 'The candidate has already submitted.');

        $token = $onboarding->issueAccessToken();
        $onboarding->activities()->create(['user_id' => $request->user()->id, 'action' => 'link_issued']);

        return back()->with('status', 'New link created. The previous link no longer works.')
            ->with('candidate_link', route('candidate.show', $token));
    }

    /** Send the case back to the candidate for missing or corrected information. */
    public function requestInfo(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        abort_unless($onboarding->isVerifying(), 422);

        $data = $request->validate(['info_request' => ['required', 'string', 'max:2000']], [
            'info_request.required' => 'Tell the candidate what to upload or correct.',
        ]);

        $onboarding->forceFill(['status' => 'info_pending', 'info_request' => $data['info_request'], 'risk_level' => null])->save();
        $token = $onboarding->issueAccessToken();
        $onboarding->activities()->create(['user_id' => $request->user()->id, 'action' => 'info_requested', 'remarks' => $data['info_request']]);

        return back()->with('status', 'Sent back to the candidate. Share the new link below.')
            ->with('candidate_link', route('candidate.show', $token));
    }

    public function addDocuments(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        $request->validate([
            'category' => ['required', Rule::in(array_keys(OnboardingDocument::CATEGORIES))],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
        ]);

        foreach ($request->file('files') as $file) {
            self::storeDocument($onboarding, $request->input('category'), $file, $request->user()->id);
        }
        $onboarding->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'documents_added',
            'meta' => ['count' => count($request->file('files')), 'category' => OnboardingDocument::CATEGORIES[$request->input('category')]],
        ]);

        return back()->with('status', 'Documents added.');
    }

    public function document(EmployeeOnboarding $onboarding, OnboardingDocument $document): StreamedResponse
    {
        Gate::authorize('view', $onboarding);
        abort_unless($document->employee_onboarding_id === $onboarding->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateCheck(Request $request, EmployeeOnboarding $onboarding, OnboardingCheck $check): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        abort_unless($check->employee_onboarding_id === $onboarding->id, 404);
        abort_unless($onboarding->isVerifying(), 422, 'Checks can be recorded once the candidate has submitted.');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(OnboardingCheck::STATUSES))],
            'method' => ['nullable', 'string', 'max:60'],
            'remarks' => [Rule::requiredIf(in_array($request->input('status'), OnboardingCheck::NEEDS_REMARKS, true)), 'nullable', 'string', 'max:2000'],
        ], ['remarks.required' => 'Add remarks explaining this outcome.']);

        $check->fill($data + ['checked_by' => $request->user()->id, 'checked_at' => now()])->save();
        $onboarding->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'check_updated',
            'remarks' => $data['remarks'] ?? null,
            'meta' => ['check' => $check->label(), 'status' => $check->statusLabel(), 'method' => $data['method'] ?? null],
        ]);

        return back()->with('status', "{$check->label()} marked {$check->statusLabel()}.");
    }

    public function storeReference(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(OnboardingReference::KINDS))],
            'contact_name' => ['required', 'string', 'max:120'],
            'organisation' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'outcome' => ['required', Rule::in(array_keys(OnboardingReference::OUTCOMES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $onboarding->references()->create($data + ['logged_by' => $request->user()->id]);
        $onboarding->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'reference_logged',
            'remarks' => trim($data['contact_name'].($data['organisation'] ? ', '.$data['organisation'] : '').': '.OnboardingReference::OUTCOMES[$data['outcome']]),
        ]);

        return back()->with('status', 'Reference contact logged.');
    }

    /** Record the risk assessment once every check has an outcome, and route the case for a decision. */
    public function assess(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        abort_unless($onboarding->isVerifying(), 422);

        $data = $request->validate([
            'risk_level' => ['required', Rule::in(array_keys(EmployeeOnboarding::RISK_LEVELS))],
            'risk_notes' => ['required', 'string', 'max:4000'],
        ], ['risk_notes.required' => 'Summarise the findings and the reason for this rating.']);

        if (! $onboarding->checks()->get()->every(fn (OnboardingCheck $c) => $c->isResolved())) {
            throw ValidationException::withMessages(['risk_level' => 'Record an outcome for every check first.']);
        }

        $onboarding->forceFill($data + [
            'status' => 'risk_review',
            'assessed_by' => $request->user()->id,
            'assessed_at' => now(),
        ])->save();
        $onboarding->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'assessed',
            'remarks' => $data['risk_notes'],
            'meta' => ['risk' => EmployeeOnboarding::RISK_LEVELS[$data['risk_level']]],
        ]);

        return back()->with('status', 'Risk assessment saved. The case is ready for a decision.');
    }

    public function decide(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('decide', $onboarding);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_notes' => [
                Rule::requiredIf($request->input('decision') === 'rejected'
                    || ($request->input('decision') === 'approved' && $onboarding->risk_level === 'high')),
                'nullable', 'string', 'max:2000',
            ],
        ], ['decision_notes.required' => 'Add a reason for this decision.']);

        $onboarding->forceFill([
            'status' => $data['decision'],
            'decision_notes' => $data['decision_notes'] ?? null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'access_token_hash' => null,
        ])->save();
        $onboarding->activities()->create(['user_id' => $request->user()->id, 'action' => $data['decision'], 'remarks' => $data['decision_notes'] ?? null]);

        return back()->with('status', $data['decision'] === 'approved' ? 'Approved. You can now create the employee\'s login.' : 'Rejected.');
    }

    /** Final employee verification report, laid out for printing / saving as PDF. */
    public function report(EmployeeOnboarding $onboarding): View
    {
        Gate::authorize('view', $onboarding);
        $onboarding->load(['department', 'position', 'manager', 'owner', 'assessor', 'decider', 'documents', 'checks.checker', 'references']);

        return view('onboarding.report', ['onboarding' => $onboarding]);
    }

    /** Creates the approved new hire's login, with the department, position and manager from the case. */
    public function createAccount(Request $request, EmployeeOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('createAccount', $onboarding);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', Password::min(8)],
        ]);

        if (! $onboarding->manager_id) {
            throw ValidationException::withMessages(['email' => 'Set the manager this employee reports to (Edit case details) first.']);
        }

        $user = DB::transaction(function () use ($onboarding, $data, $request) {
            $user = User::create([
                'name' => $onboarding->candidate_name,
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $onboarding->candidate_phone,
                'manager_id' => $onboarding->manager_id,
                'department_id' => $onboarding->department_id,
                'position_id' => $onboarding->position_id,
                'territory' => $onboarding->work_location,
                'is_active' => true,
            ]);
            $user->syncRoles([User::EMPLOYEE]);
            $onboarding->forceFill(['user_id' => $user->id])->save();
            $onboarding->activities()->create(['user_id' => $request->user()->id, 'action' => 'account_created', 'remarks' => $data['email']]);

            return $user;
        });

        return back()->with('status', "{$user->name} can now log in with {$user->email}. Share the password privately.");
    }

    public static function storeDocument(EmployeeOnboarding $onboarding, string $category, $file, ?int $userId): OnboardingDocument
    {
        return $onboarding->documents()->create([
            'category' => $category,
            'original_name' => substr($file->getClientOriginalName(), 0, 200),
            'path' => $file->store("onboarding/{$onboarding->id}", 'local'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    private function validatedCase(Request $request): array
    {
        return $request->validate([
            'candidate_name' => ['required', 'string', 'max:120'],
            'candidate_phone' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            'candidate_email' => ['nullable', 'email', 'max:150'],
            'department_id' => ['required', Rule::exists('departments', 'id')],
            'position_id' => [
                'nullable',
                Rule::exists('positions', 'id'),
                fn ($attr, $value, $fail) => $value && $request->filled('department_id')
                    && ! Position::forDepartment((int) $request->input('department_id'))->whereKey($value)->exists()
                    ? $fail('Choose a position from this department.') : null,
            ],
            'manager_id' => [
                'nullable',
                fn ($attr, $value, $fail) => $value && ! User::role(User::MANAGER)->whereKey($value)->exists() ? $fail('Choose a manager.') : null,
            ],
            'work_location' => ['nullable', 'string', 'max:120'],
            'joining_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date'],
            'owner_id' => [
                'nullable',
                fn ($attr, $value, $fail) => $value && ! $this->hrUsers()->contains('id', (int) $value) ? $fail('Choose someone from HR.') : null,
            ],
        ], ['candidate_phone.regex' => 'Enter a valid 10-digit mobile number.']);
    }

    private function formData(EmployeeOnboarding $onboarding): array
    {
        $all = Position::orderBy('display_order')->get(['id', 'name', 'abbreviation', 'department_id']);
        $standard = $all->whereNull('department_id');
        $positions = Department::pluck('id')->mapWithKeys(function ($id) use ($all, $standard) {
            $own = $all->where('department_id', $id);

            return [$id => ($own->isNotEmpty() ? $own : $standard)->values()->map(fn (Position $p) => ['id' => $p->id, 'label' => $p->label()])->all()];
        })->all();

        return [
            'onboarding' => $onboarding,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => $positions,
            'managers' => User::role(User::MANAGER)->orderBy('name')->get(['id', 'name']),
            'owners' => $this->hrUsers(),
        ];
    }

    /** People who can own a case: HR department members and super admins. */
    private function hrUsers()
    {
        return User::where('is_active', true)
            ->where(fn ($q) => $q->whereHas('department', fn ($d) => $d->where('name', Department::HR))->orWhereHas('roles', fn ($r) => $r->where('name', User::SUPER_ADMIN)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
