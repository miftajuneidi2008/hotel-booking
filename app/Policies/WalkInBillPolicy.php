<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WalkInBill;

/** Walk-in bills: desk staff and the restaurant/cashier role (BRD section 3). */
class WalkInBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Cashier;
    }

    public function view(User $user, WalkInBill $bill): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WalkInBill $bill): bool
    {
        return $this->viewAny($user);
    }
}
