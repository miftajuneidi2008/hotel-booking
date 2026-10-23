<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Development accounts only: the password for all of them is "password".
     * Change or remove them before going live.
     */
    public function run(): void
    {
        $accounts = [
            ['Owner', 'owner@example.com', UserRole::Owner],
            ['Front Desk', 'reception@example.com', UserRole::Receptionist],
            ['Test User', 'test@example.com', UserRole::Guest],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            // role and email_verified_at are not mass assignable on purpose, so unguard here only.
            User::unguarded(fn () => User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'role' => $role->value, 'email_verified_at' => now()],
            ));
        }

        $this->call(RoomSeeder::class);
    }
}
