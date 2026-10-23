<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Notifications for the signed-in user, staff and guests. Loaded from routes/web.php.
Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
});
