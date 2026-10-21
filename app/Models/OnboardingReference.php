<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A contact attempt with a previous employer, institution or personal reference. */
#[Fillable(['kind', 'contact_name', 'organisation', 'phone', 'outcome', 'notes', 'logged_by'])]
class OnboardingReference extends Model
{
    public const KINDS = [
        'previous_employer' => 'Previous employer',
        'education' => 'Education institution',
        'personal' => 'Personal reference',
    ];

    public const OUTCOMES = [
        'confirmed' => 'Confirmed details',
        'discrepancy' => 'Details did not match',
        'no_answer' => 'No answer / call back',
        'refused' => 'Declined to share',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'employee_onboarding_id');
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->outcome] ?? $this->outcome;
    }
}
