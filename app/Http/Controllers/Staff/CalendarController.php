<?php

namespace App\Http\Controllers\Staff;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Calendar view: every room as a row, every day as a column, bookings as bars (FR-06).
 */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $input = $request->validate([
            'from' => ['nullable', 'date'],
            'days' => ['nullable', 'in:7,14,31'],
        ]);

        $days = (int) ($input['days'] ?? 14);
        $from = CarbonImmutable::parse($input['from'] ?? today()->toDateString())->startOfDay();
        $to = $from->addDays($days); // first day after the window

        /** @var list<CarbonImmutable> $dates */
        $dates = array_map(fn (int $i): CarbonImmutable => $from->addDays($i), range(0, $days - 1));

        $rooms = Room::query()->with('roomType')->orderBy('floor')->orderBy('number')->get();

        // Confirmed and checked-in bookings that touch the window: the ones that hold a room.
        $bookings = Booking::query()
            ->blocking()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get()
            ->groupBy('room_id');

        $rows = $rooms->map(function (Room $room) use ($bookings, $dates, $days): array {
            $roomBookings = $bookings->get($room->id, collect());
            $segments = [];

            for ($i = 0; $i < $days;) {
                $day = $dates[$i];
                $dayString = $day->toDateString();

                /** @var Booking|null $booking */
                $booking = $roomBookings->first(
                    fn (Booking $b): bool => $b->check_in->toDateString() <= $dayString && $b->check_out->toDateString() > $dayString,
                );

                if ($booking === null) {
                    $segments[] = ['booking' => null, 'span' => 1, 'date' => $day];
                    $i++;

                    continue;
                }

                // The bar covers the nights from this day until check-out, cut at the edge of the window.
                $nights = (int) round($day->diffInDays($booking->check_out->copy()->startOfDay()));
                $span = max(1, min($nights, $days - $i));

                $segments[] = ['booking' => $booking, 'span' => $span, 'date' => $day];
                $i += $span;
            }

            return ['room' => $room, 'segments' => $segments];
        });

        // Free rooms per day (rooms that are not out of service and have no booking that night).
        $sellable = $rooms->where('status', '!=', RoomStatus::OutOfService)->count();
        $free = [];
        $all = $bookings->flatten();
        foreach ($dates as $day) {
            $dayString = $day->toDateString();
            $taken = $all->filter(
                fn (Booking $b): bool => $b->check_in->toDateString() <= $dayString && $b->check_out->toDateString() > $dayString,
            )->pluck('room_id')->unique()->count();

            $free[$dayString] = max(0, $sellable - $taken);
        }

        return view('staff.calendar', [
            'dates' => $dates,
            'rows' => $rows,
            'free' => $free,
            'days' => $days,
            'from' => $from,
            'previous' => $from->subDays($days)->toDateString(),
            'next' => $to->toDateString(),
            'today' => today()->toDateString(),
        ]);
    }
}
