<?php

use App\Http\Controllers\Admin\TaxSettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// User management: owner only (see UserPolicy). Loaded from routes/web.php.
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/role', [UserController::class, 'role'])->name('users.role');
    Route::patch('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Tax settings (VAT, service charge, TIN): owner only (manage-settings gate).
    Route::get('settings/tax', [TaxSettingsController::class, 'edit'])->middleware('can:manage-settings')->name('settings.tax.edit');
    Route::put('settings/tax', [TaxSettingsController::class, 'update'])->middleware('can:manage-settings')->name('settings.tax.update');
});
