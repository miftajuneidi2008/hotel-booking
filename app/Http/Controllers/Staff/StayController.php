<?php

namespace App\Http\Controllers\Staff;

use App\Enums\IdType;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckOutRequest;
use App\Http\Requests\GuestIdRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\StayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Check-in with ID details, check-out, and the printable stay statement (FR-11, FR-13).
 */
class StayController extends Controller
{
    public function __construct(private readonly StayService $stays) {}

    public function checkInForm(Booking $booking): View|RedirectResponse
    {
        if (! $booking->isConfirmed()) {
            return redirect()->route('staff.bookings.show', $booking)->with('error', 'Only a confirmed booking can be checked in.');
        }

        return view('staff.stay.check-in', [
            'booking' => $booking->load('room.roomType'),
            'idTypes' => IdType::cases(),
        ]);
    }

    public function checkIn(GuestIdRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->stays->checkIn($booking, $request->validated(), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.bookings.check-in', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.show', $booking)
            ->with('status', "{$booking->guest_name} is checked in to room {$booking->room->number}.");
    }

    public function saveId(GuestIdRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->stays->saveGuestId($booking, $request->validated());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.bookings.show', $booking)->with('status', 'ID details saved.');
    }

    public function checkOutForm(Booking $booking): View|RedirectResponse
    {
        if (! $booking->isCheckedIn()) {
            return redirect()->route('staff.bookings.show', $booking)->with('error', 'This guest is not checked in.');
        }

        return view('staff.stay.check-out', [
            'booking' => $booking->load('room.roomType'),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function checkOut(CheckOutRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->stays->checkOut($booking, [
                'amount' => $request->input('payment_amount'),
                'method' => $request->input('payment_method'),
                'reference' => $request->input('payment_reference'),
            ], $request->input('override_reason'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.bookings.check-out', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.bill', $booking)
            ->with('status', "{$booking->guest_name} is checked out. Room {$booking->room->number} is now marked dirty.");
    }

    /** A printable statement of the stay. It is not a tax invoice (invoices come with section 5.9). */
    public function bill(Booking $booking): View
    {
        return view('staff.stay.bill', [
            'booking' => $booking->load(['room.roomType', 'roomMoves.fromRoom', 'roomMoves.toRoom', 'checkedOutBy']),
            'property' => config('hotel.name', config('app.name')),
        ]);
    }

    /** Stopgap for housekeeping (section 5.6): mark a dirty room clean so a guest can be checked in. */
    public function markClean(Room $room): RedirectResponse
    {
        try {
            $this->stays->markRoomClean($room);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Room {$room->number} is marked clean.");
    }
}
