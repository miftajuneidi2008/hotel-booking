<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changing an existing confirmed booking (dates, guests, room, guest details).
 * Kept apart from BookingService so creating bookings is not affected.
 */
class BookingChangeService
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * Rooms that can take the new stay. The booking's own current dates count as free,
     * so a guest can keep their room and just move or extend the stay.
     *
     * @return Collection<int, Room>
     */
    public function availableRooms(Booking $booking, string $checkIn, string $checkOut, int $guests): Collection
    {
        return Room::query()
            ->where('status', '!=', RoomStatus::OutOfService->value)
            ->whereDoesntHave('bookings', fn (Builder $others) => $others
                ->blocking()
                ->overlapping($checkIn, $checkOut)
                ->where('bookings.id', '!=', $booking->id))
            ->whereHas('roomType', fn ($type) => $type->where('capacity', '>=', $guests)->whereNull('room_types.deleted_at'))
            ->with('roomType.rates')
            ->orderBy('floor')
            ->orderBy('number')
            ->get();
    }

    /**
     * Price of the changed stay. Nights the guest already booked in the same room type keep
     * their original rate (a later price change must not touch an old booking); new nights,
     * or a different room type, use today's rates.
     *
     * @return array{total: float, average: float, nights: int, breakdown: array<string, float>}
     */
    public function price(Booking $booking, Room $room, string $checkIn, string $checkOut): array
    {
        $quote = $this->bookings->quote($room->roomType, $checkIn, $checkOut);

        if ($room->room_type_id === $booking->room->room_type_id) {
            foreach ($quote['breakdown'] as $night => $amount) {
                if (isset($booking->rate_breakdown[$night])) {
                    $quote['breakdown'][$night] = (float) $booking->rate_breakdown[$night];
                }
            }

            $quote['total'] = round(array_sum($quote['breakdown']), 2);
            $quote['average'] = $quote['nights'] > 0 ? round($quote['total'] / $quote['nights'], 2) : 0.0;
        }

        return $quote;
    }

    /**
     * @param  array{room_id: int, check_in: string, check_out: string, guests: int, guest_name: string, guest_phone: string, guest_email?: ?string, notes?: ?string}  $data
     *
     * @throws BookingException
     */
    public function change(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->with('room')->firstOrFail();

            if (! $fresh->isConfirmed()) {
                throw new BookingException('Only a confirmed booking can be changed.');
            }

            $checkIn = (string) $data['check_in'];
            $checkOut = (string) $data['check_out'];
            $guests = (int) $data['guests'];

            $room = Room::query()
                ->whereKey($data['room_id'])
                ->where('status', '!=', RoomStatus::OutOfService->value)
                ->with('roomType.rates')
                ->lockForUpdate()
                ->first();

            if ($room === null) {
                throw BookingException::roomUnavailable();
            }

            if ($room->roomType->capacity < $guests) {
                throw BookingException::overCapacity($room->roomType->capacity);
            }

            $taken = $room->bookings()
                ->blocking()
                ->overlapping($checkIn, $checkOut)
                ->where('id', '!=', $fresh->id)
                ->exists();

            if ($taken) {
                throw BookingException::roomUnavailable();
            }

            $quote = $this->price($fresh, $room, $checkIn, $checkOut);

            if ((float) $fresh->deposit_amount > $quote['total']) {
                throw new BookingException('The deposit already recorded (ETB '.number_format((float) $fresh->deposit_amount, 2)
                    .') is more than the new total. Choose a longer stay or refund the deposit first.');
            }

            $fresh->update([
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'guest_name' => $data['guest_name'],
                'guest_phone' => $data['guest_phone'],
                'guest_email' => $data['guest_email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => $quote['total'],
                'rate_breakdown' => $quote['breakdown'],
            ]);

            return $fresh->load('room.roomType');
        });
    }
}
