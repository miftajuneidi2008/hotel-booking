<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Who may see the housekeeping board and change room status (BRD section 3: housekeeping sees and
 * updates room status). Desk staff can do it too, so a dirty room can be released at the front desk.
 */
class RoomStatusChangePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Housekeeping;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }
}
