<?php

use App\Http\Controllers\OnlinePaymentController;
use Illuminate\Support\Facades\Route;

// Online payment (Chapa) for guests who booked on the website. Bookings made at the desk, by phone or by an
// agent are paid with the staff payment forms instead (routes/payments.php).

// The guest presses "Pay online" on their booking page.
Route::middleware(['auth', 'throttle:10,1'])
    ->post('/my-bookings/{booking}/pay', [OnlinePaymentController::class, 'start'])
    ->name('my-bookings.pay');

// Chapa sends the guest's browser back here. Not behind "auth" on purpose: the payment is verified with
// Chapa first, and the guest is then sent to their booking page (which does need a login).
Route::get('/chapa/return/{txRef}', [OnlinePaymentController::class, 'return'])->name('chapa.return');

// Called by Chapa itself. Add /chapa/webhook to the CSRF exceptions (see INSTALL.md).
Route::get('/chapa/callback', [OnlinePaymentController::class, 'callback'])->name('chapa.callback');
Route::post('/chapa/webhook', [OnlinePaymentController::class, 'webhook'])->name('chapa.webhook');
