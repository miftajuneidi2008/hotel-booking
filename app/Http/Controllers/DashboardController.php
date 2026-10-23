<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $service): View
    {
        // Charts are for desk staff. Money figures are for owner and manager only.
        $stats = Gate::allows('manage-bookings')
            ? $service->build(DashboardStats::normalizeRange($request->query('range')), Gate::allows('manage-property'))
            : null;

        return view('dashboard', [
            'stats' => $stats,
            'ranges' => DashboardStats::RANGES,
        ]);
    }
}
