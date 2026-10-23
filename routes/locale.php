<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// Language switcher (English, Amharic, Afaan Oromoo). Loaded from routes/web.php.
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');
