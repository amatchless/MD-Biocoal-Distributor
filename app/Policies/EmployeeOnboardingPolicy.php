<?php

namespace App\Policies;

use App\Models\EmployeeOnboarding;
use App\Models\User;

class EmployeeOnboardingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageOnboarding();
    }

    public function view(User $user, EmployeeOnboarding $onboarding): bool
    {
        return $user->canManageOnboarding();
    }

    public function create(User $user): bool
    {
        return $user->canManageOnboarding();
    }

    /** Case details, documents, checks and references stay editable until the decision is made. */
    public function update(User $user, EmployeeOnboarding $onboarding): bool
    {
        return $user->canManageOnboarding() && ! $onboarding->isDecided();
    }

    public function decide(User $user, EmployeeOnboarding $onboarding): bool
    {
        return $user->canApproveOnboarding() && $onboarding->status === 'risk_review';
    }

    public function createAccount(User $user, EmployeeOnboarding $onboarding): bool
    {
        return $user->canManageOnboarding() && $onboarding->status === 'approved' && ! $onboarding->user_id;
    }
}
