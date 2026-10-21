<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category', 'original_name', 'path', 'mime', 'size', 'uploaded_by'])]
class OnboardingDocument extends Model
{
    public const CATEGORIES = [
        'photo' => 'Passport-size photograph',
        'aadhaar_masked' => 'Aadhaar card (masked copy)',
        'pan_card' => 'PAN card',
        'address_proof' => 'Address proof',
        'education_certificate' => 'Education certificates / marksheets',
        'experience_letter' => 'Experience letter',
        'relieving_letter' => 'Relieving letter',
        'salary_slips' => 'Last salary slips',
        'bank_proof' => 'Cancelled cheque / bank passbook',
        'other' => 'Other',
    ];

    /** What each upload should contain, shown to the candidate. */
    public const HINTS = [
        'photo' => 'A recent, clear photo of your face on a plain background.',
        'aadhaar_masked' => 'Download a masked Aadhaar from the UIDAI website or DigiLocker. Only the last 4 digits should be visible.',
        'address_proof' => 'Electricity bill, rent agreement, passport, voter ID or driving licence showing your current address.',
        'education_certificate' => 'Your highest qualification, and 10th / 12th marksheets.',
        'experience_letter' => 'From your most recent employer.',
        'relieving_letter' => 'If you have already left your last job.',
        'salary_slips' => 'Last 3 months, if available.',
        'bank_proof' => 'For salary credit. The account must be in your name.',
    ];

    public const ALWAYS_REQUIRED = ['photo', 'aadhaar_masked', 'pan_card', 'address_proof', 'education_certificate', 'bank_proof'];

    /** @return list<string> */
    public static function requiredFor(EmployeeOnboarding $onboarding): array
    {
        return $onboarding->is_fresher ? self::ALWAYS_REQUIRED : [...self::ALWAYS_REQUIRED, 'experience_letter'];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'employee_onboarding_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function label(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Document';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function uploadedByCandidate(): bool
    {
        return $this->uploaded_by === null;
    }
}
