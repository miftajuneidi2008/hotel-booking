<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MaintenanceIssue;
use App\Models\User;

/** Who may report and fix maintenance issues: housekeeping and desk staff. */
class MaintenanceIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Housekeeping;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function resolve(User $user, MaintenanceIssue $issue): bool
    {
        return $this->viewAny($user);
    }
}
