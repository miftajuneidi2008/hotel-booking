<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Carbon;
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

    /** How many arrivals / departures the "Today" worklists show. */
    public const ROWS_SHOWN = 5;

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

        // The period right before this one, same length, used for "vs previous" comparisons.
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);
        $prevFrom = $prevStart->copy()->startOfDay();
        $prevTo = $prevEnd->copy()->endOfDay();

        $dates = $this->daysFrom($start, $days);
        $prevDates = $this->daysFrom($prevStart, $days);
        $totalRooms = Room::query()->count();

        // ---- Right now -----------------------------------------------------
        $todayDate = $end->toDateString();

        $arrivalBookings = Booking::query()
            ->with('room.roomType')
            ->where('status', BookingStatus::Confirmed->value)
            ->whereDate('check_in', '<=', $todayDate)
            ->whereDate('check_out', '>', $todayDate)
            ->orderBy('check_in')
            ->get();

        $inHouse = Booking::query()
            ->with('room.roomType')
            ->where('status', BookingStatus::CheckedIn->value)
            ->get();

        $departureBookings = $inHouse
            ->filter(fn (Booking $b): bool => $b->check_out->toDateString() <= $todayDate)
            ->sortBy(fn (Booking $b): int => $b->check_out->timestamp)
            ->values();

        // ---- Guests: people checked in per day -----------------------------
        $checkedIn = Booking::query()
            ->whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value])
            ->whereBetween('checked_in_at', [$from, $to])
            ->get(['id', 'guests', 'checked_in_at']);

        $guestsByDay = $checkedIn
            ->groupBy(fn (Booking $b): string => $b->checked_in_at->toDateString())
            ->map(fn (Collection $rows): int => (int) $rows->sum('guests'));

        // ---- Occupancy: rooms with a guest each night ----------------------
        $stayStatuses = [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value];

        $occupancy = $this->occupancySeries(
            $dates,
            $this->staysBetween($stayStatuses, $start, $end),
            $totalRooms,
        );
        $previousOccupancy = $this->occupancySeries(
            $prevDates,
            $this->staysBetween($stayStatuses, $prevStart, $prevEnd),
            $totalRooms,
        );

        // ---- Bookings made in the period -----------------------------------
        $made = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'source', 'deposit_amount', 'deposit_method']);

        $byStatus = $made->groupBy(fn (Booking $b): string => $b->status->value);
        $bySource = $made->groupBy(fn (Booking $b): string => $b->source->value);

        // Colors carry meaning: blue = waiting, green = in the house, grey = done, red = lost.
        $statusColors = [
            BookingStatus::Confirmed->value => '#3b82f6',
            BookingStatus::CheckedIn->value => '#10b981',
            BookingStatus::CheckedOut->value => '#d4d4d4',
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

        // ---- Rooms right now ------------------------------------------------
        $roomColors = [
            RoomStatus::Clean->value => '#10b981',
            RoomStatus::Dirty->value => '#f59e0b',
            RoomStatus::Occupied->value => '#3b82f6',
            RoomStatus::OutOfService->value => '#a3a3a3',
        ];

        $roomGroups = Room::query()->get(['id', 'status'])
            ->groupBy(fn (Room $r): string => $r->status->value);

        $roomStatusBars = collect(RoomStatus::cases())->map(fn (RoomStatus $s): array => [
            'label' => $s->label(),
            'value' => $roomGroups->get($s->value, collect())->count(),
            'color' => $roomColors[$s->value],
        ])->all();

        $average = fn (array $series): int => count($series) ? (int) round(collect($series)->avg('value')) : 0;

        $stats = [
            'days' => $days,
            'from' => $start,
            'to' => $end,
            'today' => $todayDate,
            'totalRooms' => $totalRooms,
            'now' => [
                'arrivals' => $arrivalBookings->count(),
                'arrivalList' => $arrivalBookings->take(self::ROWS_SHOWN)->values(),
                'departures' => $departureBookings->count(),
                'departureList' => $departureBookings->take(self::ROWS_SHOWN)->values(),
                'inHouseRooms' => $inHouse->count(),
                'inHouseGuests' => (int) $inHouse->sum('guests'),
                'occupancy' => $totalRooms > 0 ? (int) round($inHouse->count() / $totalRooms * 100) : 0,
                'dirtyRooms' => $roomGroups->get(RoomStatus::Dirty->value, collect())->count(),
            ],
            'period' => [
                'guests' => (int) $checkedIn->sum('guests'),
                'stays' => $checkedIn->count(),
                'bookings' => $made->count(),
                'cancelled' => $byStatus->get(BookingStatus::Cancelled->value, collect())->count(),
                'avgOccupancy' => $average($occupancy),
            ],
            // Same measures for the period just before, so the cards can show a trend.
            'previous' => [
                'guests' => (int) Booking::query()
                    ->whereIn('status', $stayStatuses)
                    ->whereBetween('checked_in_at', [$prevFrom, $prevTo])
                    ->sum('guests'),
                'bookings' => Booking::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                'cancelled' => Booking::query()
                    ->where('status', BookingStatus::Cancelled->value)
                    ->whereBetween('created_at', [$prevFrom, $prevTo])
                    ->count(),
                'avgOccupancy' => $average($previousOccupancy),
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
            $stats += $this->money($dates, $from, $to, $made, $prevFrom, $prevTo);
        }

        return $stats;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function daysFrom(Carbon $start, int $days): Collection
    {
        return collect(range(0, $days - 1))->map(fn (int $i) => $start->copy()->addDays($i));
    }

    /**
     * Stays that overlap the window, with just the columns the occupancy math needs.
     *
     * @param  list<string>  $statuses
     * @return Collection<int, Booking>
     */
    private function staysBetween(array $statuses, Carbon $start, Carbon $end): Collection
    {
        return Booking::query()
            ->whereIn('status', $statuses)
            ->whereDate('check_in', '<=', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['id', 'check_in', 'check_out']);
    }

    /**
     * Share of rooms with a guest on each night.
     *
     * @param  Collection<int, Carbon>  $dates
     * @param  Collection<int, Booking>  $stays
     * @return list<array{label: string, value: float|int}>
     */
    private function occupancySeries(Collection $dates, Collection $stays, int $totalRooms): array
    {
        return $dates->map(function ($date) use ($stays, $totalRooms): array {
            $day = $date->toDateString();
            $occupied = $stays->filter(
                fn (Booking $b): bool => $b->check_in->toDateString() <= $day && $b->check_out->toDateString() > $day
            )->count();

            return [
                'label' => $date->translatedFormat('M j'),
                'value' => $totalRooms > 0 ? round(min(100, $occupied / $totalRooms * 100), 1) : 0,
            ];
        })->values()->all();
    }

    /**
     * Revenue = the bill total of stays checked out in the period.
     * Collected = deposits taken plus what was paid at check-out, by method.
     *
     * @param  Collection<int, Carbon>  $dates
     * @param  Collection<int, Booking>  $made  Bookings created in the period.
     * @return array<string, mixed>
     */
    private function money(Collection $dates, $from, $to, Collection $made, $prevFrom, $prevTo): array
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
            'previousRevenue' => round((float) Booking::query()
                ->where('status', BookingStatus::CheckedOut->value)
                ->whereBetween('checked_out_at', [$prevFrom, $prevTo])
                ->sum('total_amount'), 2),
            'collected' => round(collect($methodBars)->sum('value'), 2),
            'revenuePerDay' => $dates->map(fn ($d): array => [
                'label' => $d->translatedFormat('M j'),
                'value' => $revenueByDay->get($d->toDateString(), 0),
            ])->values()->all(),
            'methodBars' => $methodBars,
        ];
    }
}
