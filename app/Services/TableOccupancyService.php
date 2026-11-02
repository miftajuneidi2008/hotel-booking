<?php

namespace App\Services;

use App\Enums\WalkInStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\RoomTableHold;
use App\Models\User;
use App\Models\WalkInBill;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Which restaurant tables are in use, so one table never has two orders at the same time.
 *
 * - A walk-in customer holds a table while the walk-in bill is open (paid, voided or moved = free again).
 * - A hotel guest holds a table from the first order until the staff free it, or until the hold runs out
 *   (config/restaurant.php).
 * - The same customer can keep ordering at their own table: more items on the same open bill, or more
 *   orders from the same guest.
 */
class TableOccupancyService
{
    /** "  t 5 " becomes "T 5". An empty value means no table. */
    public function normalize(?string $table): ?string
    {
        $table = preg_replace('/\s+/u', ' ', trim((string) $table));

        return $table === '' || $table === null ? null : mb_strtoupper($table);
    }

    /**
     * Run the work while holding a lock on the table, so two orders saved at the same moment cannot both
     * take it. Call this outside the database transaction so the order is saved before the lock opens again.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     *
     * @throws BookingException
     */
    public function guard(?string $table, Closure $work): mixed
    {
        if ($table === null) {
            return $work();
        }

        try {
            return Cache::lock('restaurant-table:'.md5($table), 10)->block(5, $work);
        } catch (LockTimeoutException) {
            throw new BookingException(__('Another order for table :table is being saved. Wait a moment and try again.', ['table' => $table]));
        }
    }

    /**
     * Who is at the table now, other than the bill or booking that is ordering.
     *
     * @return array{kind: string, label: string}|null
     */
    public function occupant(string $table, ?int $exceptBillId = null, ?int $exceptBookingId = null): ?array
    {
        $bill = WalkInBill::query()
            ->where('status', WalkInStatus::Open->value)
            ->where('table_number', $table)
            ->when($exceptBillId, fn ($query) => $query->where('id', '!=', $exceptBillId))
            ->first();

        if ($bill !== null) {
            return ['kind' => 'walk_in', 'label' => $bill->number.($bill->customer_name ? ' · '.$bill->customer_name : '')];
        }

        $hold = RoomTableHold::query()->active()->with('booking.room')
            ->where('table_number', $table)
            ->when($exceptBookingId, fn ($query) => $query->where('booking_id', '!=', $exceptBookingId))
            ->first();

        if ($hold !== null) {
            return ['kind' => 'room', 'label' => $this->guestLabel($hold)];
        }

        return null;
    }

    /** @throws BookingException */
    public function assertFree(string $table, ?int $exceptBillId = null, ?int $exceptBookingId = null): void
    {
        $occupant = $this->occupant($table, $exceptBillId, $exceptBookingId);

        if ($occupant !== null) {
            throw new BookingException(__('Table :table already has an order (:who). A table can only have one order at a time. Choose another table, or wait until that order is paid or the table is freed.', [
                'table' => $table,
                'who' => $occupant['label'],
            ]));
        }
    }

    /**
     * Give the table to a hotel guest. A guest who ordered at another table has moved, so that table is freed.
     *
     * @throws BookingException
     */
    public function claimForRoom(Booking $booking, string $table, User $by): void
    {
        $this->assertFree($table, null, $booking->id);

        RoomTableHold::query()->active()
            ->where('booking_id', $booking->id)
            ->where('table_number', '!=', $table)
            ->update(['released_at' => now(), 'released_by' => $by->id]);

        $hold = RoomTableHold::query()->active()
            ->where('booking_id', $booking->id)
            ->where('table_number', $table)
            ->first();

        if ($hold !== null) {
            $hold->touch();

            return;
        }

        RoomTableHold::create([
            'table_number' => $table,
            'booking_id' => $booking->id,
            'created_by' => $by->id,
        ]);
    }

    public function release(RoomTableHold $hold, User $by): void
    {
        if ($hold->released_at === null) {
            $hold->update(['released_at' => now(), 'released_by' => $by->id]);
        }
    }

    /**
     * Every table in use now, for the New order screen.
     *
     * @return Collection<int, array{table: string, kind: string, who: string, hold_id: int|null}>
     */
    public function occupied(): Collection
    {
        $walkIns = WalkInBill::query()
            ->where('status', WalkInStatus::Open->value)
            ->whereNotNull('table_number')
            ->get()
            ->map(fn (WalkInBill $bill): array => [
                'table' => (string) $bill->table_number,
                'kind' => 'walk_in',
                'who' => $bill->number.($bill->customer_name ? ' · '.$bill->customer_name : ''),
                'hold_id' => null,
            ]);

        $holds = RoomTableHold::query()->active()->with('booking.room')->get()
            ->map(fn (RoomTableHold $hold): array => [
                'table' => $hold->table_number,
                'kind' => 'room',
                'who' => $this->guestLabel($hold),
                'hold_id' => $hold->id,
            ]);

        return $walkIns->concat($holds)->sortBy('table', SORT_NATURAL)->values();
    }

    private function guestLabel(RoomTableHold $hold): string
    {
        $booking = $hold->booking;

        return trim(__('Room').' '.($booking?->room?->number ?? '').' · '.($booking?->guest_name ?? ''), ' ·');
    }
}
