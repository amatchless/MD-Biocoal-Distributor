<?php

namespace App\Http\Controllers;

use App\Models\EmployeeOnboarding;
use App\Models\OnboardingDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The candidate's side of employee onboarding, reached through the private link HR shares.
 * No login: the link's token identifies the case. Candidates can save as they go and submit once complete.
 */
class CandidateOnboardingController extends Controller
{
    public function show(string $token): View
    {
        $onboarding = $this->resolve($token);
        $onboarding->load(['documents', 'department', 'position']);

        return view('candidate.onboarding', ['onboarding' => $onboarding, 'token' => $token]);
    }

    public function save(Request $request, string $token): RedirectResponse
    {
        $onboarding = $this->resolve($token);
        abort_unless($onboarding->candidateCanEdit(), 403);
        $submit = $request->input('action') === 'submit';

        $data = $this->validated($request, $submit, $onboarding);

        DB::transaction(function () use ($request, $onboarding, $data) {
            $files = $data['files'] ?? [];
            unset($data['files'], $data['consent']);

            $onboarding->fill($data)->save();

            $count = 0;
            foreach ($files as $category => $uploads) {
                foreach ($uploads as $file) {
                    EmployeeOnboardingController::storeDocument($onboarding, $category, $file, null);
                    $count++;
                }
            }

            $onboarding->activities()->create([
                'action' => $count ? 'candidate_uploaded' : 'candidate_saved',
                'meta' => array_filter(['count' => $count ?: null, 'ip' => $request->ip()]),
            ]);
        });

        if (! $submit) {
            return back()->with('status', 'Saved. You can come back to this link to finish.');
        }

        $onboarding->load('documents');
        if ($missing = $onboarding->missingDocuments()->values()->all()) {
            throw ValidationException::withMessages(['documents' => 'Please upload: '.implode(', ', $missing).'.']);
        }

        DB::transaction(function () use ($request, $onboarding) {
            $onboarding->forceFill([
                'status' => 'under_verification',
                'submitted_at' => now(),
                'consent_at' => now(),
                'consent_ip' => $request->ip(),
                'consent_user_agent' => substr((string) $request->userAgent(), 0, 300),
            ])->save();

            if ($onboarding->is_fresher) {
                $onboarding->checks()->where('check_key', 'employment')->where('status', 'pending')
                    ->update(['status' => 'not_applicable', 'remarks' => 'Fresher: no previous employment declared by the candidate.', 'checked_at' => now()]);
            }

            $onboarding->activities()->create(['action' => 'submitted', 'meta' => ['ip' => $request->ip()]]);
        });

        return back()->with('status', 'Thank you. Your details and documents have been sent to HR for verification.');
    }

    public function destroyDocument(Request $request, string $token, OnboardingDocument $document): RedirectResponse
    {
        $onboarding = $this->resolve($token);
        abort_unless($onboarding->candidateCanEdit(), 403);
        abort_unless($document->employee_onboarding_id === $onboarding->id && $document->uploadedByCandidate(), 404);

        Storage::disk('local')->delete($document->path);
        $document->delete();
        $onboarding->activities()->create(['action' => 'candidate_removed_document', 'remarks' => $document->label().': '.$document->original_name]);

        return back()->with('status', 'Document removed.');
    }

    private function resolve(string $token): EmployeeOnboarding
    {
        $onboarding = strlen($token) === 48 ? EmployeeOnboarding::findByToken($token) : null;
        abort_unless($onboarding, 404);

        return $onboarding;
    }

    private function validated(Request $request, bool $submit, EmployeeOnboarding $onboarding): array
    {
        $need = $submit ? 'required' : 'nullable';
        $fresher = $request->boolean('is_fresher');

        // Drop the blank rows the form always shows.
        $request->merge([
            'is_fresher' => $fresher,
            'pan_no' => strtoupper(trim((string) $request->input('pan_no'))) ?: null,
            'bank_ifsc' => strtoupper(trim((string) $request->input('bank_ifsc'))) ?: null,
            'education' => array_values(array_filter((array) $request->input('education', []), fn ($r) => is_array($r) && array_filter($r))),
            'employment' => $fresher ? [] : array_values(array_filter((array) $request->input('employment', []), fn ($r) => is_array($r) && array_filter($r))),
        ]);

        $data = $request->validate([
            'father_name' => [$need, 'string', 'max:120'],
            'date_of_birth' => [$need, 'date', 'before:-16 years'],
            'gender' => [$need, Rule::in(EmployeeOnboarding::GENDERS)],
            'alternate_phone' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'current_address' => [$need, 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'city' => [$need, 'string', 'max:80'],
            'state' => [$need, 'string', 'max:80'],
            'pincode' => [$need, 'digits:6'],
            'pan_no' => [$need, 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'aadhaar_last4' => [$need, 'digits:4'],
            'is_fresher' => ['boolean'],
            'education' => [$submit ? 'required' : 'nullable', 'array', 'max:6'],
            'education.*.qualification' => ['required', 'string', 'max:120'],
            'education.*.institution' => ['required', 'string', 'max:150'],
            'education.*.year' => ['required', 'integer', 'between:1960,'.(now()->year + 1)],
            'education.*.score' => ['nullable', 'string', 'max:20'],
            'employment' => [$submit && ! $fresher ? 'required' : 'nullable', 'array', 'max:6'],
            'employment.*.company' => ['required', 'string', 'max:150'],
            'employment.*.designation' => ['required', 'string', 'max:120'],
            'employment.*.from' => ['required', 'date_format:Y-m'],
            'employment.*.to' => ['nullable', 'date_format:Y-m'],
            'employment.*.contact_name' => ['nullable', 'string', 'max:120'],
            'employment.*.contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => [$need, 'string', 'max:120'],
            'emergency_contact_phone' => [$need, 'regex:/^[6-9][0-9]{9}$/'],
            'bank_name' => [$need, 'string', 'max:120'],
            'bank_account_no' => [$submit && ! $onboarding->bank_account_no ? 'required' : 'nullable', 'regex:/^[0-9]{6,18}$/'],
            'bank_ifsc' => [$need, 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'files' => ['nullable', 'array'],
            'files.*' => ['array', 'max:10'],
            'files.*.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
            'consent' => [$submit ? 'accepted' : 'nullable'],
        ], [
            'date_of_birth.before' => 'Check your date of birth.',
            'pan_no.regex' => 'Enter your 10-character PAN, e.g. ABCDE1234F.',
            'aadhaar_last4.digits' => 'Enter only the last 4 digits of your Aadhaar.',
            'alternate_phone.regex' => 'Enter a valid 10-digit mobile number.',
            'emergency_contact_phone.regex' => 'Enter a valid 10-digit mobile number.',
            'bank_account_no.regex' => 'Enter the account number using digits only.',
            'bank_ifsc.regex' => 'Enter the 11-character IFSC, e.g. SBIN0001234.',
            'education.required' => 'Add at least your highest qualification.',
            'employment.required' => 'Add your previous employment, or tick "I am a fresher".',
            'consent.accepted' => 'Please read and accept the declaration to submit.',
        ]);
        $data['files'] = array_intersect_key((array) $request->file('files', []), OnboardingDocument::CATEGORIES);
        if (blank($data['bank_account_no'] ?? null)) {
            unset($data['bank_account_no']); // blank keeps the saved number
        }

        return $data;
    }
}
