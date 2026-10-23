<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /** Remember the chosen language and go back to the page the visitor was on. */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('locales.supported', [])), 404);

        $request->session()->put('locale', $locale);

        return redirect()->back(fallback: '/');
    }
}
