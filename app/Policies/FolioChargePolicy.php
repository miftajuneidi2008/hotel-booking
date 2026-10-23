<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\FolioCharge;
use App\Models\User;

/**
 * Who may work with extra charges on a guest bill.
 * The restaurant/cashier role may see bills and add charges (BRD section 3), nothing more.
 * The approval limits for voids are checked in FolioService.
 */
class FolioChargePolicy
{
    /** See in-house bills and print them. */
    public function viewAny(User $user): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Cashier;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function void(User $user, FolioCharge $charge): bool
    {
        return $user->canManageBookings();
    }

    /** Move or split a charge to another guest bill. */
    public function transfer(User $user, FolioCharge $charge): bool
    {
        return $user->canManageBookings();
    }
}
