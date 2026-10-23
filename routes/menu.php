<?php

use App\Http\Controllers\Staff\MenuItemController;
use App\Http\Controllers\Staff\OrderController;
use App\Http\Controllers\Staff\WalkInBillController;
use Illuminate\Support\Facades\Route;

// Restaurant and extras, section 5.7. Loaded from routes/housekeeping.php. Who may do what is decided by
// MenuItemPolicy and WalkInBillPolicy, so the restaurant/cashier role can sell without the rest of the front desk.
Route::middleware(['auth', 'verified'])->prefix('staff')->name('staff.')->group(function () {
    // FR-27: the menu.
    Route::get('menu', [MenuItemController::class, 'index'])->name('menu.index');
    Route::post('menu', [MenuItemController::class, 'store'])->name('menu.store');
    Route::put('menu/{item}', [MenuItemController::class, 'update'])->name('menu.update');
    Route::delete('menu/{item}', [MenuItemController::class, 'destroy'])->name('menu.destroy');

    // FR-28: put items on a room bill or a walk-in bill.
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');

    Route::get('walk-in-bills', [WalkInBillController::class, 'index'])->name('walk-in-bills.index');
    Route::get('walk-in-bills/{bill}', [WalkInBillController::class, 'show'])->name('walk-in-bills.show');
    Route::get('walk-in-bills/{bill}/receipt', [WalkInBillController::class, 'receipt'])->name('walk-in-bills.receipt');
    Route::post('walk-in-bills/{bill}/pay', [WalkInBillController::class, 'pay'])->name('walk-in-bills.pay');
    Route::post('walk-in-bills/{bill}/move', [WalkInBillController::class, 'move'])->name('walk-in-bills.move');
    Route::patch('walk-in-bills/{bill}/void', [WalkInBillController::class, 'void'])->name('walk-in-bills.void');
    Route::delete('walk-in-bill-lines/{line}', [WalkInBillController::class, 'removeLine'])->name('walk-in-bills.lines.destroy');
});
