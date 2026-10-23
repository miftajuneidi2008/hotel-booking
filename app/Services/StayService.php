<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomMove;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything that happens while a guest is in the house (section 5.3):
 * check-in with ID, check-out, changing room, extending or shortening the stay.
 *
 * Rooms and the booking are locked inside a transaction so two people at the desk
 * cannot, for example, check the same guest out twice or move two guests into one room.
 */
class StayService
{
    public function __construct(private readonly BookingService $bookings) {}

    // ------------------------------------------------------------------
    // FR-11 Check-in
    // ------------------------------------------------------------------

    /**
     * Check in a confirmed booking and save the guest's ID or passport details.
     *
     * @param  array{id_type: string, id_number: string, nationality?: ?string}  $id
     *
     * @throws BookingException
     */
    public function checkIn(Booking $booking, array $id, User $by): Booking
    {
        return DB::transaction(function () use ($booking, $id, $by): Booking {
            [$fresh, $room] = $this->lock($booking);

            if (! $fresh->isConfirmed()) {
                throw BookingException::cannotCheckIn();
            }

            $today = today()->toDateString();

            if ($fresh->check_in->toDateString() > $today) {
                throw BookingException::notArrivalDay($fresh->check_in->format('M j, Y'));
            }

            if ($fresh->check_out->toDateString() <= $today) {
                throw BookingException::stayOver();
            }

            if ($room->status !== RoomStatus::Clean) {
                throw BookingException::roomNotReady($room->number);
            }

            $fresh->update([
                'status' => BookingStatus::CheckedIn,
                'checked_in_at' => now(),
                'checked_in_by' => $by->id,
                'id_type' => $id['id_type'],
                'id_number' => $id['id_number'],
                'nationality' => $id['nationality'] ?? null,
            ]);

            $room->update(['status' => RoomStatus::Occupied]);

            return $fresh->load('room.roomType');
        });
    }

    /**
     * Save or correct ID details, for example for a walk-in who was checked in straight away.
     *
     * @param  array{id_type: string, id_number: string, nationality?: ?string}  $id
     *
     * @throws BookingException
     */
    public function saveGuestId(Booking $booking, array $id): Booking
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
            throw new BookingException('ID details can only be saved for a confirmed or checked-in booking.');
        }

        $booking->update([
            'id_type' => $id['id_type'],
            'id_number' => $id['id_number'],
            'nationality' => $id['nationality'] ?? null,
        ]);

        return $booking;
    }

    // ------------------------------------------------------------------
    // FR-13 Check-out
    // ------------------------------------------------------------------

    /**
     * Check a guest out, close the bill and set the room to dirty.
     *
     * The guest may pay what is still owed here. If something stays unpaid, only an owner or
     * manager can let the guest leave, and they must record the reason (business rule 4).
     *
     * @param  array{amount?: float|int|string|null, method?: ?string, reference?: ?string}  $payment
     *
     * @throws BookingException
     */
    public function checkOut(Booking $booking, array $payment, ?string $overrideReason, User $by): Booking
    {
        return DB::transaction(function () use ($booking, $payment, $overrideReason, $by): Booking {
            [$fresh, $room] = $this->lock($booking);

            if (! $fresh->isCheckedIn()) {
                throw BookingException::notCheckedIn();
            }

            // The bill must cover exactly the nights stayed, so dates are fixed first.
            $today = today()->toDateString();
            $leaves = $fresh->check_out->toDateString();

            if ($leaves > $today) {
                throw new BookingException('The booking runs until '.$fresh->check_out->format('M j, Y')
                    .'. Use "Change stay" to shorten it first, so the bill only has the nights the guest stayed.');
            }

            if ($leaves < $today) {
                throw new BookingException('The booking ended on '.$fresh->check_out->format('M j, Y')
                    .'. Use "Change stay" to extend it first, so the extra nights are on the bill.');
            }

            $balance = $fresh->balanceDue();
            $amount = round((float) ($payment['amount'] ?? 0), 2);

            if ($amount < 0) {
                throw new BookingException('The payment cannot be negative.');
            }

            if ($amount > $balance + 0.004) {
                throw new BookingException('The payment (ETB '.number_format($amount, 2)
                    .') is more than the balance due (ETB '.number_format(max($balance, 0), 2).').');
            }

            if ($amount > 0 && blank($payment['method'] ?? null)) {
                throw new BookingException('Choose how the guest paid.');
            }

            $unpaid = round(max($balance - $amount, 0), 2);
            $reason = trim((string) $overrideReason);

            if ($unpaid > 0) {
                if (! $by->canApproveCheckout()) {
                    throw new BookingException('ETB '.number_format($unpaid, 2)
                        .' is still unpaid. Only a manager or the owner can let a guest leave with an unpaid balance.');
                }

                if ($reason === '') {
                    throw new BookingException('Enter the reason for letting the guest leave with an unpaid balance.');
                }
            }

            $fresh->update([
                'status' => BookingStatus::CheckedOut,
                'checked_out_at' => now(),
                'checked_out_by' => $by->id,
                'settlement_amount' => round((float) $fresh->settlement_amount + $amount, 2),
                'settlement_method' => $amount > 0 ? $payment['method'] : $fresh->settlement_method,
                'settlement_reference' => $amount > 0 ? ($payment['reference'] ?? null) : $fresh->settlement_reference,
                'checkout_unpaid_amount' => $unpaid,
                'checkout_override_reason' => $unpaid > 0 ? $reason : null,
                'checkout_approved_by' => $unpaid > 0 ? $by->id : null,
            ]);

            $room->update(['status' => RoomStatus::Dirty]);

            return $fresh->load('room.roomType');
        });
    }

    // ------------------------------------------------------------------
    // FR-12 Change room during a stay
    // ------------------------------------------------------------------

    /**
     * Clean rooms that are free for the rest of the stay (today until check-out).
     *
     * @return Collection<int, Room>
     */
    public function roomsForMove(Booking $booking): Collection
    {
        return Room::query()
            ->availableBetween(today()->toDateString(), $booking->check_out->toDateString())
            ->where('status', RoomStatus::Clean->value)
            ->where('id', '!=', $booking->room_id)
            ->whereHas('roomType', fn ($type) => $type->where('capacity', '>=', $booking->guests)->whereNull('room_types.deleted_at'))
            ->with('roomType.rates')
            ->orderBy('floor')
            ->orderBy('number')
            ->get();
    }

    /**
     * New price after moving: nights already stayed are never repriced. The remaining nights keep
     * their rate in a room of the same type and use today's rate for a different type.
     *
     * @return array{total: float, breakdown: array<string, float>}
     */
    public function movePrice(Booking $booking, Room $target): array
    {
        $breakdown = $booking->rate_breakdown;

        if ($target->room_type_id !== $booking->room->room_type_id) {
            $today = today()->toDateString();
            $remaining = $this->bookings->quote($target->roomType, $today, $booking->check_out->toDateString())['breakdown'];

            $breakdown = array_filter($breakdown, fn (float|int $amount, string $night): bool => $night < $today, ARRAY_FILTER_USE_BOTH) + $remaining;
            ksort($breakdown);
        }

        return ['total' => round(array_sum($breakdown), 2), 'breakdown' => $breakdown];
    }

    /**
     * @throws BookingException
     */
    public function moveRoom(Booking $booking, Room $target, string $reason, User $by): Booking
    {
        return DB::transaction(function () use ($booking, $target, $reason, $by): Booking {
            // Both rooms are locked in id order so two moves cannot deadlock.
            $ids = collect([$booking->room_id, $target->id])->sort()->values()->all();
            Room::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->with('room.roomType')->firstOrFail();

            if (! $fresh->isCheckedIn()) {
                throw new BookingException('Only a guest who is checked in can change rooms.');
            }

            $today = today()->toDateString();

            if ($fresh->check_out->toDateString() <= $today) {
                throw new BookingException('The guest leaves today, so there is no night left to move.');
            }

            $target = Room::query()->with('roomType.rates')->findOrFail($target->id);
            $old = $fresh->room;

            if ($target->id === $old->id) {
                throw new BookingException('The guest is already in that room.');
            }

            if ($target->status !== RoomStatus::Clean) {
                throw BookingException::roomNotReady($target->number);
            }

            if ($target->roomType->capacity < $fresh->guests) {
                throw BookingException::overCapacity($target->roomType->capacity);
            }

            $taken = $target->bookings()->blocking()->overlapping($today, $fresh->check_out->toDateString())->exists();

            if ($taken) {
                throw BookingException::roomUnavailable();
            }

            $price = $this->movePrice($fresh, $target);
            $this->assertPaymentsFit($fresh, $price['total']);

            RoomMove::create([
                'booking_id' => $fresh->id,
                'from_room_id' => $old->id,
                'to_room_id' => $target->id,
                'moved_on' => $today,
                'reason' => $reason,
                'moved_by' => $by->id,
            ]);

            $fresh->update([
                'room_id' => $target->id,
                'rate_breakdown' => $price['breakdown'],
                'total_amount' => $price['total'],
            ]);

            $old->update(['status' => RoomStatus::Dirty]);
            $target->update(['status' => RoomStatus::Occupied]);

            return $fresh->load('room.roomType');
        });
    }

    // ------------------------------------------------------------------
    // FR-14 Extend or shorten a stay in progress
    // ------------------------------------------------------------------

    /**
     * The first date after the current check-out on which the guest's room is taken by someone else.
     */
    public function roomFreeUntil(Booking $booking): ?string
    {
        $next = $booking->room->bookings()
            ->blocking()
            ->where('id', '!=', $booking->id)
            ->whereDate('check_in', '>=', $booking->check_out->toDateString())
            ->orderBy('check_in')
            ->first();

        return $next?->check_in->toDateString();
    }

    /**
     * Move the check-out date of a guest who is in the house. Extra nights are priced at today's
     * rate; removed nights come off the bill. Nights that stay on the bill keep their price.
     *
     * @throws BookingException
     */
    public function changeStay(Booking $booking, string $newCheckOut): Booking
    {
        return DB::transaction(function () use ($booking, $newCheckOut): Booking {
            [$fresh, $room] = $this->lock($booking);
            $fresh->load('room.roomType.rates');

            if (! $fresh->isCheckedIn()) {
                throw BookingException::notCheckedIn();
            }

            $today = today()->toDateString();
            $old = $fresh->check_out->toDateString();

            if ($newCheckOut === $old) {
                throw new BookingException('That is already the check-out date.');
            }

            if ($newCheckOut < $today) {
                throw new BookingException('The new check-out date cannot be before today.');
            }

            if ($newCheckOut <= $fresh->check_in->toDateString()) {
                throw new BookingException('The stay must have at least one night.');
            }

            $breakdown = $fresh->rate_breakdown;

            if ($newCheckOut > $old) {
                $taken = $room->bookings()
                    ->blocking()
                    ->overlapping($old, $newCheckOut)
                    ->where('id', '!=', $fresh->id)
                    ->exists();

                if ($taken) {
                    throw new BookingException("Room {$room->number} is booked by another guest during the extra nights. Move the guest to another room first, or choose an earlier date.");
                }

                $breakdown += $this->bookings->quote($fresh->room->roomType, $old, $newCheckOut)['breakdown'];
            } else {
                $breakdown = array_filter($breakdown, fn (float|int $amount, string $night): bool => $night < $newCheckOut, ARRAY_FILTER_USE_BOTH);
            }

            ksort($breakdown);
            $total = round(array_sum($breakdown), 2);

            $this->assertPaymentsFit($fresh, $total);

            $fresh->update([
                'check_out' => $newCheckOut,
                'rate_breakdown' => $breakdown,
                'total_amount' => $total,
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Stopgap until housekeeping (section 5.6) has its own screen: front desk can mark a dirty room clean.
     *
     * @throws BookingException
     */
    public function markRoomClean(Room $room): Room
    {
        if ($room->status !== RoomStatus::Dirty) {
            throw new BookingException("Room {$room->number} is not marked dirty.");
        }

        $room->update(['status' => RoomStatus::Clean]);

        return $room;
    }

    /**
     * @return array{0: Booking, 1: Room}
     */
    private function lock(Booking $booking): array
    {
        $room = Room::query()->whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
        $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

        return [$fresh, $room];
    }

    /** A change must never leave the guest having paid more than the new total. */
    private function assertPaymentsFit(Booking $booking, float $newTotal): void
    {
        if ($booking->amountPaid() > $newTotal + 0.004) {
            throw new BookingException('The guest has already paid ETB '.number_format($booking->amountPaid(), 2)
                .', which is more than the new total (ETB '.number_format($newTotal, 2).'). Refund the difference first.');
        }
    }
}
