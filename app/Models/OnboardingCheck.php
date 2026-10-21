<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One verification item on an employee case. Its outcome is separate from the case status. */
#[Fillable(['check_key', 'status', 'method', 'remarks', 'checked_by', 'checked_at'])]
class OnboardingCheck extends Model
{
    /** The checks from the proposal's employee module, in the order HR works through them. */
    public const CHECKS = [
        'aadhaar' => ['label' => 'Aadhaar', 'hint' => 'Masked Aadhaar matches the name, photo and date of birth. Verify through DigiLocker / UIDAI where possible.'],
        'pan' => ['label' => 'PAN', 'hint' => 'PAN card matches the name and date of birth. Verify on the income tax e-filing portal.'],
        'mobile' => ['label' => 'Mobile number', 'hint' => 'Candidate reached on the given number (call or OTP).'],
        'address' => ['label' => 'Address', 'hint' => 'Address proof matches the current address given.'],
        'education' => ['label' => 'Education', 'hint' => 'Certificates reviewed; confirm with the institution where needed.'],
        'employment' => ['label' => 'Previous employment', 'hint' => 'Experience / relieving letters reviewed and confirmed with the previous employer.'],
        'identity' => ['label' => 'Identity authenticity', 'hint' => 'Photo and documents belong to the same person; no signs of tampering.'],
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'verified' => 'Verified',
        'discrepancy' => 'Discrepancy',
        'not_applicable' => 'Not applicable',
        'unable_to_verify' => 'Unable to verify',
    ];

    /** Outcomes that need a written reason. */
    public const NEEDS_REMARKS = ['discrepancy', 'not_applicable', 'unable_to_verify'];

    public const METHODS = ['Document review', 'DigiLocker / UIDAI', 'Government portal', 'Phone call', 'Email', 'In person', 'Third-party provider'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'employee_onboarding_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function label(): string
    {
        return self::CHECKS[$this->check_key]['label'] ?? $this->check_key;
    }

    public function hint(): string
    {
        return self::CHECKS[$this->check_key]['hint'] ?? '';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isResolved(): bool
    {
        return ! in_array($this->status, ['pending', 'in_progress'], true);
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            'verified' => 'bg-brand-100 text-brand-800',
            'discrepancy' => 'bg-red-100 text-red-800',
            'unable_to_verify' => 'bg-orange-100 text-orange-800',
            'in_progress' => 'bg-amber-100 text-amber-800',
            'not_applicable' => 'bg-gray-100 text-gray-600',
            default => 'bg-sky-100 text-sky-800',
        };
    }
}
