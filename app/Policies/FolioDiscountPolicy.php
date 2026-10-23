<?php

namespace App\Policies;

use App\Models\FolioDiscount;
use App\Models\User;

/** Who may give or remove a discount. The approval limits are checked in FolioService. */
class FolioDiscountPolicy
{
    public function create(User $user): bool
    {
        return $user->canManageBookings();
    }

    public function void(User $user, FolioDiscount $discount): bool
    {
        return $user->canManageBookings();
    }
}
