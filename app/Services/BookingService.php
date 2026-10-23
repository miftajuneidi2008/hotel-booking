<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Rooms free for the whole stay that can sleep $guests people.
     *
     * @return Collection<int, Room>
     */
    public function availableRooms(string $checkIn, string $checkOut, int $guests = 1, ?int $roomTypeId = null): Collection
    {
        return Room::query()
            ->availableBetween($checkIn, $checkOut)
            ->when($roomTypeId, fn ($query) => $query->where('room_type_id', $roomTypeId))
            ->whereHas('roomType', fn ($query) => $query->where('capacity', '>=', $guests)->whereNull('room_types.deleted_at'))
            ->with(['roomType.rates', 'photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('floor')
            ->orderBy('number')
            ->get();
    }

    /**
     * One entry per room type that still has a free room, with the price for the stay.
     *
     * @return Collection<int, array{type: RoomType, available: int, photo: ?string, quote: array{total: float, average: float, nights: int, breakdown: array<string, float>}}>
     */
    public function availableTypes(string $checkIn, string $checkOut, int $guests): Collection
    {
        return $this->availableRooms($checkIn, $checkOut, $guests)
            ->groupBy('room_type_id')
            ->map(function (Collection $rooms) use ($checkIn, $checkOut): array {
                /** @var Room $first */
                $first = $rooms->first();

                return [
                    'type' => $first->roomType,
                    'available' => $rooms->count(),
                    'photo' => $rooms->flatMap->photos->first()?->path,
                    'quote' => $this->quote($first->roomType, $checkIn, $checkOut),
                ];
            })
            ->sortBy(fn (array $row): float => $row['quote']['total'])
            ->values();
    }

    /**
     * Price each night of the stay with the rate that applies that night.
     *
     * @return array{total: float, average: float, nights: int, breakdown: array<string, float>}
     */
    public function quote(RoomType $type, string $checkIn, string $checkOut): array
    {
        $type->loadMissing('rates');

        $breakdown = [];
        $night = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();

        for (; $night->lessThan($end); $night = $night->addDay()) {
            $breakdown[$night->toDateString()] = (float) $type->rateForDate($night);
        }

        $nights = count($breakdown);
        $total = round(array_sum($breakdown), 2);

        return [
            'total' => $total,
            'average' => $nights > 0 ? round($total / $nights, 2) : 0.0,
            'nights' => $nights,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Guest self-service: the guest picks a room type and the system assigns a free room.
     *
     * @param  array{check_in: string, check_out: string, guests: int, guest_name: string, guest_phone: string, guest_email?: ?string, notes?: ?string}  $data
     *
     * @throws BookingException
     */
    public function bookRoomType(RoomType $type, array $data, User $customer): Booking
    {
        return $this->reserve(
            candidateRoomIds: $type->rooms()->pluck('id')->all(),
            data: $data + ['source' => BookingSource::Online],
            customer: $customer,
        );
    }

    /**
     * Desk booking: the receptionist picks a specific room.
     *
     * @param  array{check_in: string, check_out: string, guests: int, guest_name: string, guest_phone: string, guest_email?: ?string, notes?: ?string, source: BookingSource, deposit_amount?: float|int|string|null, deposit_method?: ?PaymentMethod, deposit_reference?: ?string, check_in_now?: bool}  $data
     *
     * @throws BookingException
     */
    public function bookRoom(Room $room, array $data, User $staff): Booking
    {
        return $this->reserve([$room->id], $data, customer: null, staff: $staff);
    }

    /**
     * Create the booking inside a transaction. The candidate rooms are locked first, then the
     * overlap check is made, so two people booking at the same moment cannot both get the room
     * (on MySQL / PostgreSQL; SQLite serialises writes by itself).
     *
     * @param  list<int>  $candidateRoomIds
     * @param  array<string, mixed>  $data
     *
     * @throws BookingException
     */
    private function reserve(array $candidateRoomIds, array $data, ?User $customer = null, ?User $staff = null): Booking
    {
        $checkIn = (string) $data['check_in'];
        $checkOut = (string) $data['check_out'];
        $guests = (int) $data['guests'];

        return DB::transaction(function () use ($candidateRoomIds, $data, $customer, $staff, $checkIn, $checkOut, $guests): Booking {
            // One guest cannot hold two rooms for the same nights (this includes a guest who is in the house now).
            $this->assertGuestHasNoOverlappingStay($data, $customer, $checkIn, $checkOut);

            $rooms = Room::query()
                ->whereKey($candidateRoomIds)
                ->where('status', '!=', RoomStatus::OutOfService->value)
                ->with('roomType.rates')
                ->orderBy('floor')
                ->orderBy('number')
                ->lockForUpdate()
                ->get();

            $room = $rooms->first(fn (Room $room): bool => $room->roomType->capacity >= $guests
                && ! $room->bookings()->blocking()->overlapping($checkIn, $checkOut)->exists());

            if ($room === null) {
                // A single chosen room that is free but too small deserves its own message.
                $tooSmall = $rooms->first(fn (Room $room): bool => $room->roomType->capacity < $guests
                    && ! $room->bookings()->blocking()->overlapping($checkIn, $checkOut)->exists());

                throw $tooSmall
                    ? BookingException::overCapacity($tooSmall->roomType->capacity)
                    : BookingException::roomUnavailable();
            }

            $quote = $this->quote($room->roomType, $checkIn, $checkOut);

            $checkInNow = ($data['check_in_now'] ?? false) && $checkIn === today()->toDateString();

            if ($checkInNow && $room->status !== RoomStatus::Clean) {
                throw BookingException::roomNotReady($room->number);
            }

            $deposit = round((float) ($data['deposit_amount'] ?? 0), 2);

            if ($deposit > $quote['total']) {
                throw BookingException::depositTooHigh();
            }

            $booking = Booking::create([
                'room_id' => $room->id,
                'user_id' => $customer?->id,
                'created_by' => $staff?->id,
                'source' => $data['source'],
                'status' => $checkInNow ? BookingStatus::CheckedIn : BookingStatus::Confirmed,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'guest_name' => $data['guest_name'],
                'guest_phone' => $data['guest_phone'],
                'guest_email' => $data['guest_email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => $quote['total'],
                'rate_breakdown' => $quote['breakdown'],
                'deposit_amount' => $deposit,
                'deposit_method' => $deposit > 0 ? ($data['deposit_method'] ?? null) : null,
                'deposit_reference' => $deposit > 0 ? ($data['deposit_reference'] ?? null) : null,
                'checked_in_at' => $checkInNow ? now() : null,
            ]);

            if ($checkInNow) {
                $room->update(['status' => RoomStatus::Occupied]);
            }

            return $booking->load('room.roomType');
        });
    }

    /**
     * A guest who is already booked or checked in for any of these nights must not get a second room.
     * Extra rooms for the same party go through a group booking; a different room for the same guest
     * goes through "Change room". The guest is matched by account (online) or by phone number.
     * Agents book for many different guests with their own phone, so they are matched by account only.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BookingException
     */
    private function assertGuestHasNoOverlappingStay(array $data, ?User $customer, string $checkIn, string $checkOut): void
    {
        $source = $data['source'] ?? null;
        $matchByPhone = $source !== BookingSource::Agent;
        $phone = $matchByPhone ? $this->phoneKey((string) ($data['guest_phone'] ?? '')) : '';

        $existing = Booking::query()
            ->blocking()
            ->overlapping($checkIn, $checkOut)
            ->get(['id', 'user_id', 'guest_phone', 'status'])
            ->first(fn (Booking $other): bool => ($customer !== null && $other->user_id !== null && (int) $other->user_id === (int) $customer->id)
                || ($phone !== '' && $this->phoneKey((string) $other->guest_phone) === $phone));

        if ($existing === null) {
            return;
        }

        throw $existing->isCheckedIn()
            ? BookingException::guestInHouse()
            : BookingException::guestAlreadyBooked();
    }

    /** Digits only, last 9, so +251 911 000 000 and 0911 000 000 are the same number. */
    private function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * @throws BookingException
     */
    public function cancel(Booking $booking, string $reason, User $by): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $by): Booking {
            // Re-read under a lock so a double click cannot cancel twice.
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $fresh->isConfirmed()) {
                throw BookingException::cannotCancel();
            }

            $fresh->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancellation_reason' => $reason,
            ]);

            return $fresh;
        });
    }
}
