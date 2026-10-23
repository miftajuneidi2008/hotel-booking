<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeBookingRequest;
use App\Models\Booking;
use App\Services\BookingChangeService;
use App\Support\StayRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Change a confirmed booking: new dates, number of guests, another room, or guest details.
 */
class BookingChangeController extends Controller
{
    public function __construct(private readonly BookingChangeService $changes) {}

    public function edit(Request $request, Booking $booking): View|RedirectResponse
    {
        if (! $booking->isConfirmed()) {
            return redirect()
                ->route('staff.bookings.show', $booking)
                ->with('error', 'Only a confirmed booking can be changed.');
        }

        $booking->loadMissing('room.roomType');

        // First visit: start from the booking's own stay. After "Find rooms": use the new search.
        $input = $request->hasAny(['checkin', 'checkout', 'guests'])
            ? Validator::make($request->query(), ChangeBookingRequest::stayRulesFor($booking))->validate()
            : [
                'checkin' => $booking->check_in->toDateString(),
                'checkout' => $booking->check_out->toDateString(),
                'guests' => $booking->guests,
            ];

        [$checkIn, $checkOut, $guests] = StayRules::normalise($input);

        $rooms = $this->changes->availableRooms($booking, $checkIn, $checkOut, $guests);

        $prices = [];
        foreach ($rooms as $room) {
            $prices[$room->id] = $this->changes->price($booking, $room, $checkIn, $checkOut);
        }

        return view('staff.bookings.edit', [
            'booking' => $booking,
            'search' => ['checkin' => $checkIn, 'checkout' => $checkOut, 'guests' => $guests],
            'rooms' => $rooms,
            'prices' => $prices,
        ]);
    }

    public function update(ChangeBookingRequest $request, Booking $booking): RedirectResponse
    {
        $input = $request->validated();
        [$checkIn, $checkOut, $guests] = StayRules::normalise($input);

        try {
            $this->changes->change($booking, [
                'room_id' => (int) $input['room_id'],
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'guest_name' => $input['guest_name'],
                'guest_phone' => $input['guest_phone'],
                'guest_email' => $input['guest_email'] ?? null,
                'notes' => $input['notes'] ?? null,
            ]);
        } catch (BookingException $e) {
            return redirect()
                ->route('staff.bookings.edit', [$booking, 'checkin' => $checkIn, 'checkout' => $checkOut, 'guests' => $guests])
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.show', $booking)
            ->with('status', "Booking {$booking->reference} was changed.");
    }
}
