<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
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

        // Guests get their current or next stay instead of charts.
        /** @var Booking|null $nextStay */
        $nextStay = $stats
            ? null
            : $request->user()->bookings()
                ->with('room.roomType')
                ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
                ->whereDate('check_out', '>=', today())
                ->orderBy('check_in')
                ->first();

        return view('dashboard', [
            'stats' => $stats,
            'ranges' => DashboardStats::RANGES,
            'nextStay' => $nextStay,
        ]);
    }
}
