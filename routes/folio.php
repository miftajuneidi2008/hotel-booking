<?php

use App\Http\Controllers\Staff\FolioChargeController;
use App\Http\Controllers\Staff\FolioController;
use App\Http\Controllers\Staff\FolioDiscountController;
use Illuminate\Support\Facades\Route;

// Guest bill (folio), section 5.4. Who may do what is decided by the Folio policies, so the
// restaurant/cashier role can add charges without getting the rest of the front desk.
Route::middleware(['auth', 'verified'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('folios', [FolioController::class, 'index'])->name('folios.index');

    Route::get('bookings/{booking}/folio', [FolioController::class, 'show'])->name('folio.show');
    Route::get('bookings/{booking}/folio/receipt', [FolioController::class, 'receipt'])->name('folio.receipt');
    Route::get('bookings/{booking}/folio/invoice', [FolioController::class, 'invoice'])->name('folio.invoice');

    Route::post('bookings/{booking}/folio/charges', [FolioChargeController::class, 'store'])->name('folio.charges.store');
    Route::patch('folio/charges/{charge}/void', [FolioChargeController::class, 'void'])->name('folio.charges.void');
    Route::post('folio/charges/{charge}/transfer', [FolioChargeController::class, 'transfer'])->name('folio.charges.transfer');

    Route::post('bookings/{booking}/folio/discounts', [FolioDiscountController::class, 'store'])->name('folio.discounts.store');
    Route::patch('folio/discounts/{discount}/void', [FolioDiscountController::class, 'void'])->name('folio.discounts.void');
});
