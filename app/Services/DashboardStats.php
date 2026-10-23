<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * Numbers for the dashboard. Everything is grouped in PHP (not with SQL date
 * functions) so it works the same on SQLite, MySQL and PostgreSQL.
 */
class DashboardStats
{
    /** Day ranges the dashboard offers. */
    public const RANGES = [7, 30, 90];

    public const DEFAULT_RANGE = 30;

    public static function normalizeRange(mixed $days): int
    {
        $days = (int) $days;

        return in_array($days, self::RANGES, true) ? $days : self::DEFAULT_RANGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $days, bool $withMoney): array
    {
        $days = self::normalizeRange($days);
        $end = today();
        $start = $end->copy()->subDays($days - 1);
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        $dates = collect(range(0, $days - 1))->map(fn (int $i) => $start->copy()->addDays($i));
        $totalRooms = Room::query()->count();

        // ---- Right now -----------------------------------------------------
        $todayDate = $end->toDateString();

        $arrivals = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereDate('check_in', '<=', $todayDate)
            ->whereDate('check_out', '>', $todayDate)
            ->count();

        $inHouse = Booking::query()
            ->where('status', BookingStatus::CheckedIn->value)
            ->get(['id', 'guests', 'check_out']);

        $departures = $inHouse->filter(fn (Booking $b): bool => $b->check_out->toDateString() <= $todayDate)->count();

        // ---- Guests: people checked in per day -----------------------------
        $checkedIn = Booking::query()
            ->whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value])
            ->whereBetween('checked_in_at', [$from, $to])
            ->get(['id', 'guests', 'checked_in_at']);

        $guestsByDay = $checkedIn
            ->groupBy(fn (Booking $b): string => $b->checked_in_at->toDateString())
            ->map(fn (Collection $rows): int => (int) $rows->sum('guests'));

        // ---- Occupancy: rooms with a guest each night ----------------------
        $stays = Booking::query()
            ->whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value])
            ->whereDate('check_in', '<=', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['id', 'check_in', 'check_out']);

        $occupancy = $dates->map(function ($date) use ($stays, $totalRooms): array {
            $day = $date->toDateString();
            $occupied = $stays->filter(
                fn (Booking $b): bool => $b->check_in->toDateString() <= $day && $b->check_out->toDateString() > $day
            )->count();

            return [
                'label' => $date->translatedFormat('M j'),
                'value' => $totalRooms > 0 ? round(min(100, $occupied / $totalRooms * 100), 1) : 0,
            ];
        })->values()->all();

        // ---- Bookings made in the period -----------------------------------
        $made = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'source', 'deposit_amount', 'deposit_method']);

        $byStatus = $made->groupBy(fn (Booking $b): string => $b->status->value);
        $bySource = $made->groupBy(fn (Booking $b): string => $b->source->value);

        $statusColors = [
            BookingStatus::Confirmed->value => '#6366f1',
            BookingStatus::CheckedIn->value => '#10b981',
            BookingStatus::CheckedOut->value => '#9ca3af',
            BookingStatus::Cancelled->value => '#ef4444',
        ];

        $statusSegments = collect(BookingStatus::cases())->map(fn (BookingStatus $s): array => [
            'label' => $s->label(),
            'value' => $byStatus->get($s->value, collect())->count(),
            'color' => $statusColors[$s->value],
        ])->all();

        $sourceBars = collect(BookingSource::cases())->map(fn (BookingSource $s): array => [
            'label' => $s->label(),
            'value' => $bySource->get($s->value, collect())->count(),
        ])->all();

        $roomStatusBars = Room::query()->get(['id', 'status'])
            ->groupBy(fn (Room $r): string => $r->status->value)
            ->pipe(fn (Collection $groups): array => collect(RoomStatus::cases())->map(fn (RoomStatus $s): array => [
                'label' => $s->label(),
                'value' => $groups->get($s->value, collect())->count(),
            ])->all());

        $stats = [
            'days' => $days,
            'from' => $start,
            'to' => $end,
            'totalRooms' => $totalRooms,
            'now' => [
                'arrivals' => $arrivals,
                'departures' => $departures,
                'inHouseRooms' => $inHouse->count(),
                'inHouseGuests' => (int) $inHouse->sum('guests'),
                'occupancy' => $totalRooms > 0 ? round($inHouse->count() / $totalRooms * 100) : 0,
            ],
            'period' => [
                'guests' => (int) $checkedIn->sum('guests'),
                'stays' => $checkedIn->count(),
                'bookings' => $made->count(),
                'cancelled' => $byStatus->get(BookingStatus::Cancelled->value, collect())->count(),
                'avgOccupancy' => count($occupancy) ? round(collect($occupancy)->avg('value')) : 0,
            ],
            'guestsPerDay' => $dates->map(fn ($d): array => [
                'label' => $d->translatedFormat('M j'),
                'value' => $guestsByDay->get($d->toDateString(), 0),
            ])->values()->all(),
            'occupancyPerDay' => $occupancy,
            'statusSegments' => $statusSegments,
            'sourceBars' => $sourceBars,
            'roomStatusBars' => $roomStatusBars,
        ];

        if ($withMoney) {
            $stats += $this->money($dates, $from, $to, $made);
        }

        return $stats;
    }

    /**
     * Revenue = the bill total of stays checked out in the period.
     * Collected = deposits taken plus what was paid at check-out, by method.
     *
     * @param  Collection<int, \Illuminate\Support\Carbon>  $dates
     * @param  Collection<int, Booking>  $made  Bookings created in the period.
     * @return array<string, mixed>
     */
    private function money(Collection $dates, $from, $to, Collection $made): array
    {
        $checkedOut = Booking::query()
            ->where('status', BookingStatus::CheckedOut->value)
            ->whereBetween('checked_out_at', [$from, $to])
            ->get(['id', 'total_amount', 'checked_out_at', 'settlement_amount', 'settlement_method']);

        $revenueByDay = $checkedOut
            ->groupBy(fn (Booking $b): string => $b->checked_out_at->toDateString())
            ->map(fn (Collection $rows): float => round((float) $rows->sum('total_amount'), 2));

        $collected = [];
        $add = function ($method, $amount) use (&$collected): void {
            if ($method === null || (float) $amount <= 0) {
                return;
            }
            $collected[$method->value] ??= ['label' => $method->label(), 'value' => 0.0];
            $collected[$method->value]['value'] += (float) $amount;
        };

        // Deposits on bookings made in the period that were not cancelled.
        foreach ($made as $booking) {
            if ($booking->status !== BookingStatus::Cancelled) {
                $add($booking->deposit_method, $booking->deposit_amount);
            }
        }
        foreach ($checkedOut as $booking) {
            $add($booking->settlement_method, $booking->settlement_amount);
        }

        $methodBars = collect(PaymentMethod::cases())->map(fn ($m): array => [
            'label' => $m->label(),
            'value' => round($collected[$m->value]['value'] ?? 0, 2),
        ])->all();

        return [
            'revenue' => round((float) $checkedOut->sum('total_amount'), 2),
            'collected' => round(collect($methodBars)->sum('value'), 2),
            'revenuePerDay' => $dates->map(fn ($d): array => [
                'label' => $d->translatedFormat('M j'),
                'value' => $revenueByDay->get($d->toDateString(), 0),
            ])->values()->all(),
            'methodBars' => $methodBars,
        ];
    }
}
