<?php

use App\Http\Controllers\Staff\CashSummaryController;
use App\Http\Controllers\Staff\PaymentController;
use Illuminate\Support\Facades\Route;

// Payments, refunds and the daily cash summary, section 5.5. Who may do what is decided by PaymentPolicy.
Route::middleware(['auth', 'verified'])->prefix('staff')->name('staff.')->group(function () {
    Route::post('bookings/{booking}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('bookings/{booking}/refunds', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::patch('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

    Route::get('cash-summary', [CashSummaryController::class, 'index'])->name('cash-summary');
    Route::get('cash-summary/print', [CashSummaryController::class, 'print'])->name('cash-summary.print');
    Route::post('cash-summary/close', [CashSummaryController::class, 'close'])->name('cash-summary.close');
});
