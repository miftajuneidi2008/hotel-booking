<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\View\View;

/**
 * Today at the front desk: who is arriving, who is in the house, who is leaving.
 */
class FrontDeskController extends Controller
{
    public function index(): View
    {
        $today = today()->toDateString();

        $arrivals = Booking::query()
            ->with('room.roomType')
            ->where('status', BookingStatus::Confirmed->value)
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>', $today)
            ->orderBy('check_in')
            ->get();

        $inHouse = Booking::query()
            ->with('room.roomType')
            ->where('status', BookingStatus::CheckedIn->value)
            ->get()
            ->sortBy(fn (Booking $b): string => str_pad($b->room->number, 6, '0', STR_PAD_LEFT))
            ->values();

        $departures = $inHouse->filter(fn (Booking $b): bool => $b->check_out->toDateString() <= $today)->values();

        return view('staff.front-desk', [
            'arrivals' => $arrivals,
            'inHouse' => $inHouse,
            'departures' => $departures,
            'today' => $today,
        ]);
    }
}
