<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner and manager edit rooms, room types and rates.
        Gate::define('manage-property', fn (User $user): bool => $user->canManageProperty());

        // Owner and manager may let a guest check out with an unpaid balance.
        Gate::define('approve-unpaid-checkout', fn (User $user): bool => $user->canApproveCheckout());

        // Desk staff (owner, manager, receptionist) may book rooms for guests.
        Gate::define('manage-bookings', fn (User $user): bool => $user->canManageBookings());
        // Only the owner changes tax settings (VAT, service charge, TIN).
        Gate::define('manage-settings', fn (User $user): bool => $user->role === UserRole::Owner);

        // Settings saved by the owner in the admin screens replace the values in config/tax.php and .env.
        $this->app->make(SettingsService::class)->applyToConfig();

        Route::pushMiddlewareToGroup('web', EnsureUserIsActive::class);

        // Show the site in the language the visitor picked (English, Amharic or Afaan Oromoo).
        Route::pushMiddlewareToGroup('web', SetLocale::class);
    }
}
