<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Group and company bookings (FR-10): several rooms for one customer and one set of dates.
 * Every room becomes an ordinary booking, so double-booking protection, cancelling and
 * reports work as they do for single bookings.
 */
class GroupBookingService
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * All rooms are booked together or not at all.
     *
     * @param  list<int>  $roomIds
     * @param  array<int|string, int|string>  $guestsPerRoom  room id => number of guests
     * @param  array{check_in: string, check_out: string, name: string, contact_name?: ?string, contact_phone: string, contact_email?: ?string, notes?: ?string, source: BookingSource}  $data
     *
     * @throws BookingException
     */
    public function create(array $roomIds, array $guestsPerRoom, array $data, User $staff): BookingGroup
    {
        $roomIds = array_values(array_unique(array_map('intval', $roomIds)));
        $checkIn = (string) $data['check_in'];
        $checkOut = (string) $data['check_out'];

        return DB::transaction(function () use ($roomIds, $guestsPerRoom, $data, $staff, $checkIn, $checkOut): BookingGroup {
            // Locked in id order so two people booking groups at the same time cannot deadlock.
            $rooms = Room::query()
                ->whereKey($roomIds)
                ->where('status', '!=', RoomStatus::OutOfService->value)
                ->with('roomType.rates')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($rooms->count() !== count($roomIds)) {
                throw new BookingException('One of the chosen rooms is not available. Please search again.');
            }

            foreach ($rooms as $room) {
                $guests = (int) ($guestsPerRoom[$room->id] ?? 1);

                if ($room->roomType->capacity < $guests) {
                    throw new BookingException("Room {$room->number} sleeps up to {$room->roomType->capacity} guests.");
                }

                if ($room->bookings()->blocking()->overlapping($checkIn, $checkOut)->exists()) {
                    throw new BookingException("Room {$room->number} is no longer available for those dates. Nothing was booked.");
                }
            }

            $group = BookingGroup::create([
                'name' => $data['name'],
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $staff->id,
            ]);

            foreach ($rooms->sortBy(['floor', 'number']) as $room) {
                $quote = $this->bookings->quote($room->roomType, $checkIn, $checkOut);

                Booking::create([
                    'room_id' => $room->id,
                    'booking_group_id' => $group->id,
                    'created_by' => $staff->id,
                    'source' => $data['source'],
                    'status' => BookingStatus::Confirmed,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guests' => (int) ($guestsPerRoom[$room->id] ?? 1),
                    // The group name is shown wherever a guest name is shown (lists, calendar, search).
                    'guest_name' => $data['name'],
                    'guest_phone' => $data['contact_phone'],
                    'guest_email' => $data['contact_email'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'total_amount' => $quote['total'],
                    'rate_breakdown' => $quote['breakdown'],
                    'deposit_amount' => 0,
                ]);
            }

            return $group->load('bookings.room.roomType');
        });
    }

    /**
     * Cancel every room of the group that is still confirmed. Returns how many were cancelled.
     *
     * @throws BookingException
     */
    public function cancel(BookingGroup $group, string $reason, User $by): int
    {
        return DB::transaction(function () use ($group, $reason, $by): int {
            $cancelled = 0;

            foreach ($group->bookings()->get() as $booking) {
                if ($booking->isConfirmed()) {
                    $this->bookings->cancel($booking, $reason, $by);
                    $cancelled++;
                }
            }

            if ($cancelled === 0) {
                throw BookingException::cannotCancel();
            }

            return $cancelled;
        });
    }
}
