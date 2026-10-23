<?php

use App\Http\Controllers\Staff\HousekeepingController;
use App\Http\Controllers\Staff\MaintenanceIssueController;
use Illuminate\Support\Facades\Route;

// Housekeeping, section 5.6. Who may do what is decided by the policies, so the housekeeping role
// gets these pages without getting the rest of the front desk.
Route::middleware(['auth', 'verified'])->prefix('housekeeping')->name('housekeeping.')->group(function () {
    Route::get('/', [HousekeepingController::class, 'index'])->name('board');
    Route::get('rooms/{room}', [HousekeepingController::class, 'show'])->name('rooms.show');
    Route::patch('rooms/{room}/status', [HousekeepingController::class, 'status'])->name('rooms.status');

    Route::get('issues', [MaintenanceIssueController::class, 'index'])->name('issues.index');
    Route::post('rooms/{room}/issues', [MaintenanceIssueController::class, 'store'])->name('issues.store');
    Route::patch('issues/{issue}/resolve', [MaintenanceIssueController::class, 'resolve'])->name('issues.resolve');
});

// Restaurant and extras, section 5.7, has its own routes file.
require __DIR__.'/menu.php';
