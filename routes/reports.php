<?php

use App\Http\Controllers\Staff\InvoiceController;
use App\Http\Controllers\Staff\ReportController;
use Illuminate\Support\Facades\Route;

// Reports (section 5.8) and tax invoices (section 5.9). Loaded from routes/web.php.
Route::middleware(['auth', 'verified'])->prefix('staff')->name('staff.')->group(function () {
    // Reports: owner and manager only.
    Route::middleware('can:manage-property')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        // Each report has a screen, a print page (Save as PDF) and an Excel download.
        foreach (['daily', 'range', 'users'] as $kind) {
            Route::get("reports/{$kind}", [ReportController::class, 'show'])->defaults('kind', $kind)->name("reports.{$kind}");
            Route::get("reports/{$kind}/print", [ReportController::class, 'print'])->defaults('kind', $kind)->name("reports.{$kind}.print");
            Route::get("reports/{$kind}/xlsx", [ReportController::class, 'xlsx'])->defaults('kind', $kind)->name("reports.{$kind}.xlsx");
        }
    });

    // Invoices. Who may do what is decided by InvoicePolicy.
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/export/{format}', [InvoiceController::class, 'export'])->whereIn('format', ['xlsx', 'json'])->name('invoices.export');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/json', [InvoiceController::class, 'json'])->name('invoices.json');
    Route::patch('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::post('bookings/{booking}/invoice', [InvoiceController::class, 'issue'])->name('invoices.issue');
});
