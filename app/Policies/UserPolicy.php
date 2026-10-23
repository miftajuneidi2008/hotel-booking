<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * User management is for the owner only. A manager could otherwise promote themselves
 * to owner or lock the owner out. To let managers in, change isOwner() below.
 * Rules about "yourself" and "the last owner" are checked in UserManagementService.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isOwner($user);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->isOwner($user);
    }

    private function isOwner(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }
}
