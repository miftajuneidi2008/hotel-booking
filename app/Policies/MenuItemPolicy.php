<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\User;

/**
 * The menu: everyone who sells can see it, only the owner and manager change items and prices.
 */
class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Cashier;
    }

    public function create(User $user): bool
    {
        return $user->canManageProperty();
    }

    public function update(User $user, MenuItem $item): bool
    {
        return $user->canManageProperty();
    }

    public function delete(User $user, MenuItem $item): bool
    {
        return $user->canManageProperty();
    }
}
