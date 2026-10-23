<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\UserManagementException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Edit, activate, deactivate, delete and change the role of a user.
 *
 * Protections: you cannot lock yourself out (deactivate, delete or change your own role), and the
 * system always keeps at least one active owner. Delete only hides the user (business rule 7).
 */
class UserManagementService
{
    /** @param  array{name: string, email: string, password?: ?string}  $data */
    public function update(User $user, array $data): User
    {
        $user->fill([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password']; // hashed by the model cast
        }

        $user->save();

        return $user;
    }

    public function changeRole(User $user, UserRole $role, User $by): User
    {
        return DB::transaction(function () use ($user, $role, $by): User {
            $fresh = $this->lock($user);

            if ($fresh->role === $role) {
                throw new UserManagementException('That user already has this role.');
            }

            if ($fresh->id === $by->id) {
                throw new UserManagementException('You cannot change your own role. Ask another owner to do it.');
            }

            $this->assertNotLastOwner($fresh, 'Make someone else an owner first, then change this role.');

            $fresh->role = $role; // not mass assignable on purpose
            $fresh->save();

            return $fresh;
        });
    }

    public function activate(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $fresh = $this->lock($user);

            if ((bool) $fresh->is_active) {
                throw new UserManagementException('This user is already active.');
            }

            $fresh->is_active = true;
            $fresh->save();

            return $fresh;
        });
    }

    public function deactivate(User $user, User $by): User
    {
        return DB::transaction(function () use ($user, $by): User {
            $fresh = $this->lock($user);

            if ($fresh->id === $by->id) {
                throw new UserManagementException('You cannot deactivate your own account.');
            }

            if (! (bool) $fresh->is_active) {
                throw new UserManagementException('This user is already deactivated.');
            }

            $this->assertNotLastOwner($fresh, 'Make someone else an owner before deactivating this one.');

            $fresh->is_active = false;
            $fresh->setRememberToken(null);
            $fresh->save();
            $this->endSessions($fresh);

            return $fresh;
        });
    }

    public function delete(User $user, User $by): User
    {
        return DB::transaction(function () use ($user, $by): User {
            $fresh = $this->lock($user);

            if ($fresh->id === $by->id) {
                throw new UserManagementException('You cannot delete your own account here.');
            }

            $this->assertNotLastOwner($fresh, 'Make someone else an owner before deleting this one.');

            $fresh->is_active = false;
            $fresh->deleted_at = now();
            $fresh->setRememberToken(null);
            $fresh->save();
            $this->endSessions($fresh);

            return $fresh;
        });
    }

    private function lock(User $user): User
    {
        $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        if ($fresh->deleted_at !== null) {
            throw new UserManagementException('This user was deleted.');
        }

        return $fresh;
    }

    /** Refuse a change that would leave the property without an active owner. */
    private function assertNotLastOwner(User $target, string $hint): void
    {
        $isActiveOwner = $target->role === UserRole::Owner && (bool) $target->is_active;

        if (! $isActiveOwner) {
            return;
        }

        // Lock the owner rows so two owners cannot remove each other at the same moment.
        $owners = User::query()
            ->where('role', UserRole::Owner->value)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->pluck('id');

        if ($owners->count() <= 1) {
            throw new UserManagementException('This is the only active owner. '.$hint);
        }
    }

    /** Sign the user out of every device now (only needed when sessions are kept in the database). */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
