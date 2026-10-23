<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeStayRequest;
use App\Http\Requests\MoveRoomRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\StayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Changes to a guest who is already in the house: another room (FR-12), a longer or shorter stay (FR-14).
 */
class InHouseController extends Controller
{
    public function __construct(private readonly StayService $stays) {}

    public function moveForm(Booking $booking): View|RedirectResponse
    {
        if (! $booking->isCheckedIn()) {
            return redirect()->route('staff.bookings.show', $booking)->with('error', 'Only a guest who is checked in can change rooms.');
        }

        $booking->load('room.roomType');
        $rooms = $this->stays->roomsForMove($booking);

        return view('staff.stay.move-room', [
            'booking' => $booking,
            'rooms' => $rooms,
            'prices' => $rooms->mapWithKeys(fn (Room $room): array => [$room->id => $this->stays->movePrice($booking, $room)['total']])->all(),
        ]);
    }

    public function move(MoveRoomRequest $request, Booking $booking): RedirectResponse
    {
        $target = Room::query()->findOrFail($request->integer('room_id'));

        try {
            $this->stays->moveRoom($booking, $target, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.bookings.move-room', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.show', $booking)
            ->with('status', "Guest moved to room {$target->number}. The old room is marked dirty.");
    }

    public function stayForm(Booking $booking): View|RedirectResponse
    {
        if (! $booking->isCheckedIn()) {
            return redirect()->route('staff.bookings.show', $booking)->with('error', 'Only a guest who is checked in can have the stay changed here. For a confirmed booking use Change booking.');
        }

        $booking->load('room.roomType');

        return view('staff.stay.change-stay', [
            'booking' => $booking,
            'freeUntil' => $this->stays->roomFreeUntil($booking),
        ]);
    }

    public function updateStay(ChangeStayRequest $request, Booking $booking): RedirectResponse
    {
        $newCheckOut = date('Y-m-d', strtotime((string) $request->input('checkout')));

        try {
            $this->stays->changeStay($booking, $newCheckOut);
        } catch (BookingException $e) {
            return redirect()->route('staff.bookings.stay', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.show', $booking)
            ->with('status', 'The stay now ends on '.date('M j, Y', strtotime($newCheckOut)).'. The bill was updated.');
    }
}
