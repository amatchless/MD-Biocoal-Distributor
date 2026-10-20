<?php

namespace App\Policies;

use App\Models\Dealer;
use App\Models\User;

class DealerPolicy
{
    public function view(User $user, Dealer $dealer): bool
    {
        $ids = $user->visibleCreatorIds();

        return $ids === null || in_array($dealer->created_by, $ids, true);
    }

    /** The employee who onboarded can correct details until a manager has started reviewing (or sent it back). */
    public function update(User $user, Dealer $dealer): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $dealer->created_by === $user->id && in_array($dealer->status, ['submitted', 'needs_info'], true);
    }

    public function addDocuments(User $user, Dealer $dealer): bool
    {
        return $user->isSuperAdmin() || $dealer->created_by === $user->id;
    }

    public function review(User $user, Dealer $dealer): bool
    {
        return ($user->isManager() || $user->isSuperAdmin()) && $this->view($user, $dealer);
    }
}
