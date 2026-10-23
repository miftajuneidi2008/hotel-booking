<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ChargeCategory;
use App\Enums\PaymentMethod;
use App\Enums\WalkInStatus;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\FolioDiscount;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Models\WalkInBill;
use App\Models\WalkInBillLine;
use App\Support\PropertyTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reports, section 5.8.
 *
 * - FR-29 daily report: arrivals, departures, occupancy, income by payment method, unpaid bills.
 * - FR-30 occupancy and income for a date range.
 * - FR-32 report by user, for cashier control.
 * - FR-31 every report can be turned into Excel sheets (see sheets()) and has a print page for PDF.
 *
 * Every report is returned in one shape (title, summary, tables, notes) so the same screen, print page and
 * Excel file work for all of them.
 *
 * "Income" is money actually received: payments minus refunds on room bills (section 5.5), deposits taken
 * when a booking is made, money paid at check-out, and restaurant walk-in bills that were paid. Voided
 * entries are left out. A day runs from midnight to midnight in the property's timezone (PropertyTime).
 */
class ReportService
{
    /** Longest period one report may cover. */
    public const MAX_DAYS = 366;

    private const SOURCES = [
        'payment' => 'Room bill payments',
        'deposit' => 'Deposits',
        'checkout' => 'Paid at check-out',
        'walk_in' => 'Restaurant walk-in bills',
    ];

    // ------------------------------------------------------------------
    // FR-29 Daily report
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function daily(string $date): array
    {
        $day = CarbonImmutable::parse($date);
        $totalRooms = Room::query()->count();

        $arrivals = Booking::query()->with('room')
            ->whereDate('check_in', $date)
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->orderBy('guest_name')->get();

        $departures = Booking::query()->with('room')
            ->whereDate('check_out', $date)
            ->whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value])
            ->orderBy('guest_name')->get();

        $stays = $this->stays($date, $date);
        $occupied = $stays->count();
        $roomRevenue = round((float) $stays->sum(fn (Booking $b): float => (float) ($b->rate_breakdown[$date] ?? 0)), 2);
        $occupancy = $this->percent($occupied, $totalRooms);

        $entries = $this->entries($date, $date);
        $money = $this->money($entries);
        $unpaid = $this->unpaidBills();

        $tables = [];

        $tables[] = $this->table('Arrivals', [
            ['Booking', 'text'], ['Guest', 'text'], ['Room', 'text'], ['Guests', 'int'], ['Nights', 'int'], ['Status', 'text'],
        ], $arrivals->map(fn (Booking $b): array => [
            $this->bookingLink($b), $b->guest_name, $b->room->number, $b->guests, $b->nights(),
            match ($b->status) {
                BookingStatus::Confirmed => 'Not checked in',
                BookingStatus::CheckedIn => 'In house',
                default => 'Checked out',
            },
        ])->all(), null, 'No arrivals on this day.');

        $tables[] = $this->table('Departures', [
            ['Booking', 'text'], ['Guest', 'text'], ['Room', 'text'], ['Status', 'text'], ['Balance due now', 'money'],
        ], $departures->map(fn (Booking $b): array => [
            $this->bookingLink($b), $b->guest_name, $b->room->number,
            $b->status === BookingStatus::CheckedOut ? 'Checked out' : 'Still in house',
            max($b->balanceDue(), 0.0),
        ])->all(), null, 'No departures on this day.');

        $tables[] = $this->departmentTable($date, $date, $roomRevenue);

        $tables[] = $this->table('Income by payment method', [
            ['Method', 'text'], ['Received', 'money'], ['Refunded', 'money'], ['Net', 'money'],
        ], $this->methodRows($money), ['Total', $money['in'], $money['out'], $money['net']]);

        $tables[] = $this->table('Income by source', [
            ['Source', 'text'], ['Received', 'money'], ['Refunded', 'money'], ['Net', 'money'],
        ], $this->sourceRows($money), ['Total', $money['in'], $money['out'], $money['net']]);

        $tables[] = $this->table('Unpaid bills (right now)', [
            ['Booking', 'text'], ['Guest', 'text'], ['Room', 'text'], ['Status', 'text'],
            ['Bill total', 'money'], ['Paid', 'money'], ['Balance due', 'money'],
        ], $unpaid->map(fn (array $row): array => [
            $this->bookingLink($row['booking']), $row['booking']->guest_name, $row['booking']->room->number,
            $row['booking']->status->label(), $row['total'], $row['paid'], $row['balance'],
        ])->all(), ['Total', '', '', '', '', '', round((float) $unpaid->sum('balance'), 2)], 'No unpaid bills.');

        $tables[] = $this->table('Money entries of the day', [
            ['Time', 'text'], ['Source', 'text'], ['Reference', 'text'], ['Booking', 'text'], ['Method', 'text'],
            ['User', 'text'], ['Amount', 'money'],
        ], $entries->sortBy('at')->map(fn (array $e): array => [
            PropertyTime::local($e['at'])->format('H:i'), self::SOURCES[$e['source']].($e['direction'] === 'out' ? ' (refund)' : ''),
            $e['ref'], $e['booking_ref'] ?? '', $e['method']->label(), $e['user']?->name ?? '-',
            $e['direction'] === 'out' ? -$e['amount'] : $e['amount'],
        ])->values()->all(), null, 'No money was recorded on this day.');

        return [
            'title' => 'Daily report',
            'subtitle' => $day->format('l, F j, Y').' - '.PropertyTime::timezone().' time',
            'summary' => [
                ['Arrivals', (string) $arrivals->count()],
                ['Departures', (string) $departures->count()],
                ['Rooms occupied', $occupied.' of '.$totalRooms],
                ['Occupancy', number_format($occupancy, 1).'%'],
                ['Room revenue', 'ETB '.number_format($roomRevenue, 2)],
                ['Net income received', 'ETB '.number_format($money['net'], 2)],
                ['Unpaid bills', 'ETB '.number_format((float) $unpaid->sum('balance'), 2)],
            ],
            'tables' => $tables,
            'notes' => [
                'All amounts are in ETB. Income is money received minus refunds; voided entries are not counted.',
                'Deposits are counted on the day the booking was made, including deposits on bookings that were cancelled later (the money was received).',
                'Unpaid bills show what is owed right now by guests in the house and by guests who left with an approved unpaid balance, whatever day is chosen.',
                'Occupancy counts rooms with a checked-in or checked-out stay on that night.',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // FR-30 Occupancy and income for a date range
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function range(string $from, string $to): array
    {
        $totalRooms = Room::query()->count();
        $days = $this->days($from, $to);

        $occupied = array_fill_keys($days, 0);
        $revenue = array_fill_keys($days, 0.0);

        foreach ($this->stays($from, $to) as $stay) {
            $cursor = CarbonImmutable::parse(max($stay->check_in->toDateString(), $from));
            $last = CarbonImmutable::parse(min($stay->check_out->copy()->subDay()->toDateString(), $to));

            for (; $cursor <= $last; $cursor = $cursor->addDay()) {
                $key = $cursor->toDateString();
                $occupied[$key]++;
                $revenue[$key] += (float) ($stay->rate_breakdown[$key] ?? 0);
            }
        }

        $entries = $this->entries($from, $to);
        $money = $this->money($entries);
        $byDate = $entries->groupBy('date');

        $rows = [];

        foreach ($days as $key) {
            $dayMoney = $this->money($byDate->get($key, collect()));

            $row = [
                CarbonImmutable::parse($key)->format('D, M j, Y'),
                $occupied[$key],
                $this->percent($occupied[$key], $totalRooms),
                round($revenue[$key], 2),
            ];

            foreach (PaymentMethod::cases() as $method) {
                $row[] = $dayMoney['by_method'][$method->value]['net'];
            }

            $row[] = $dayMoney['net'];
            $rows[] = $row;
        }

        $nights = array_sum($occupied);
        $revenueTotal = round(array_sum($revenue), 2);
        $averageOccupancy = $this->percent($nights, $totalRooms * count($days));

        $footer = ['Total', $nights, $averageOccupancy, $revenueTotal];
        foreach (PaymentMethod::cases() as $method) {
            $footer[] = $money['by_method'][$method->value]['net'];
        }
        $footer[] = $money['net'];

        $columns = [['Date', 'text'], ['Rooms occupied', 'int'], ['Occupancy', 'percent'], ['Room revenue', 'money']];
        foreach (PaymentMethod::cases() as $method) {
            $columns[] = [$method->label(), 'money'];
        }
        $columns[] = ['Net income', 'money'];

        return [
            'title' => 'Occupancy and income',
            'subtitle' => CarbonImmutable::parse($from)->format('M j, Y').' to '.CarbonImmutable::parse($to)->format('M j, Y')
                .' ('.count($days).' '.(count($days) === 1 ? 'day' : 'days').')',
            'summary' => [
                ['Room nights sold', (string) $nights],
                ['Average occupancy', number_format($averageOccupancy, 1).'%'],
                ['Room revenue', 'ETB '.number_format($revenueTotal, 2)],
                ['Average room rate', 'ETB '.number_format($nights > 0 ? $revenueTotal / $nights : 0, 2)],
                ['Net income received', 'ETB '.number_format($money['net'], 2)],
            ],
            'tables' => [
                $this->table('Day by day', $columns, $rows, $footer, 'No days in this range.'),
                $this->departmentTable($from, $to, $revenueTotal),
                $this->table('Income by payment method', [
                    ['Method', 'text'], ['Received', 'money'], ['Refunded', 'money'], ['Net', 'money'],
                ], $this->methodRows($money), ['Total', $money['in'], $money['out'], $money['net']]),
                $this->table('Income by source', [
                    ['Source', 'text'], ['Received', 'money'], ['Refunded', 'money'], ['Net', 'money'],
                ], $this->sourceRows($money), ['Total', $money['in'], $money['out'], $money['net']]),
            ],
            'notes' => [
                'All amounts are in ETB. Room revenue is the price of the nights stayed (checked-in and checked-out stays). Net income is money received minus refunds on the day it was recorded.',
                'Average occupancy = room nights sold / (rooms x days). Rooms out of service still count as rooms.',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // FR-32 Report by user
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function byUser(string $from, string $to, ?int $userId = null): array
    {
        [$start, $end] = PropertyTime::range($from, $to);

        $entries = $this->entries($from, $to)
            ->when($userId, fn (Collection $c): Collection => $c->filter(fn (array $e): bool => $e['user_id'] === $userId));

        $voided = Payment::query()->with(['booking', 'creator', 'voider'])
            ->whereNotNull('voided_at')
            ->where('paid_at', '>=', $start)->where('paid_at', '<', $end)
            ->when($userId, fn ($q) => $q->where('created_by', $userId))
            ->orderBy('paid_at')->get();

        $discounts = FolioDiscount::query()
            ->whereNull('voided_at')
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->when($userId, fn ($q) => $q->where('created_by', $userId))
            ->get(['id', 'created_by', 'amount']);

        $byEntries = $entries->groupBy(fn (array $e): int => (int) $e['user_id']);
        $byVoided = $voided->groupBy(fn (Payment $p): int => (int) $p->created_by);
        $byDiscounts = $discounts->groupBy(fn (FolioDiscount $d): int => (int) $d->created_by);

        $ids = $byEntries->keys()->merge($byVoided->keys())->merge($byDiscounts->keys())->unique()->values();
        $users = User::query()->whereIn('id', $ids->filter()->all())->get()->keyBy('id');

        $columns = [['User', 'text'], ['Role', 'text'], ['Entries', 'int']];
        foreach (PaymentMethod::cases() as $method) {
            $columns[] = [$method->label(), 'money'];
        }
        array_push($columns, ['Received', 'money'], ['Refunded', 'money'], ['Net', 'money'], ['Voided entries', 'int'], ['Voided amount', 'money'], ['Discounts given', 'money']);

        $rows = $ids->map(function (int $id) use ($byEntries, $byVoided, $byDiscounts, $users): array {
            $money = $this->money($byEntries->get($id, collect()));
            $voidedOfUser = $byVoided->get($id, collect());
            $user = $users->get($id);

            $row = [$user?->name ?? 'Online / unknown', $user?->role->label() ?? '-', $byEntries->get($id, collect())->count()];
            foreach (PaymentMethod::cases() as $method) {
                $row[] = $money['by_method'][$method->value]['net'];
            }

            return array_merge($row, [
                $money['in'], $money['out'], $money['net'],
                $voidedOfUser->count(), round((float) $voidedOfUser->sum('amount'), 2),
                round((float) $byDiscounts->get($id, collect())->sum('amount'), 2),
            ]);
        })->sortBy(fn (array $row): string => mb_strtolower((string) $row[0]))->values()->all();

        $all = $this->money($entries);
        $footer = ['All users', '', $entries->count()];
        foreach (PaymentMethod::cases() as $method) {
            $footer[] = $all['by_method'][$method->value]['net'];
        }
        array_push($footer, $all['in'], $all['out'], $all['net'], $voided->count(), round((float) $voided->sum('amount'), 2), round((float) $discounts->sum('amount'), 2));

        $timezone = PropertyTime::timezone();

        return [
            'title' => 'Report by user',
            'subtitle' => CarbonImmutable::parse($from)->format('M j, Y').' to '.CarbonImmutable::parse($to)->format('M j, Y')
                .($userId ? ' - '.($users->get($userId)?->name ?? User::query()->find($userId)?->name ?? 'one user') : ' - all users'),
            'summary' => [
                ['People with entries', (string) $ids->filter()->count()],
                ['Received', 'ETB '.number_format($all['in'], 2)],
                ['Refunded', 'ETB '.number_format($all['out'], 2)],
                ['Net', 'ETB '.number_format($all['net'], 2)],
                ['Voided entries', $voided->count().' (ETB '.number_format((float) $voided->sum('amount'), 2).')'],
                ['Discounts given', 'ETB '.number_format((float) $discounts->sum('amount'), 2)],
            ],
            'tables' => [
                $this->table('By user', $columns, $rows, $footer, 'No money entries, voids or discounts in this period.'),
                $this->table('Voided payments and refunds', [
                    ['Recorded', 'text'], ['No.', 'text'], ['Booking', 'text'], ['Type', 'text'], ['Method', 'text'],
                    ['Amount', 'money'], ['Recorded by', 'text'], ['Voided by', 'text'], ['Reason', 'text'],
                ], $voided->map(fn (Payment $p): array => [
                    PropertyTime::local($p->paid_at)->format('M j, H:i'), $p->number, $this->bookingLink($p->booking),
                    $p->isRefund() ? 'Refund' : 'Payment', $p->method->label(), (float) $p->amount,
                    $p->creator?->name ?? '-', $p->voider?->name ?? '-', $p->void_reason ?? '',
                ])->all(), null, 'No voided entries in this period.'),
            ],
            'notes' => [
                'Entries include room bill payments and refunds, deposits (credited to the person who made the booking), money paid at check-out (credited to the person who checked the guest out) and paid restaurant walk-in bills. Times are '.$timezone.' time.',
                'Voided entries are payments and refunds recorded in the period that were voided later. Discounts given are active discounts applied in the period.',
                'Per-method columns show net money (received minus refunded). All amounts are in ETB.',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // FR-31 Excel
    // ------------------------------------------------------------------

    /**
     * The sheets for XlsxWriter: one summary sheet, then one sheet per table.
     *
     * @param  array<string, mixed>  $report
     * @return list<array{name: string, widths?: list<int|float>, rows: list<list<mixed>>}>
     */
    public function sheets(array $report): array
    {
        $rows = [[['v' => $report['title'], 's' => 'title']], [$report['subtitle']], []];

        foreach ($report['summary'] as [$label, $value]) {
            $rows[] = [['v' => $label, 's' => 'bold'], $value];
        }

        $rows[] = [];

        foreach ($report['notes'] as $note) {
            $rows[] = [$note];
        }

        $sheets = [['name' => 'Summary', 'widths' => [28, 28], 'rows' => $rows]];

        foreach ($report['tables'] as $table) {
            $rows = [];
            $rows[] = array_map(fn (array $c): array => ['v' => $c['label'], 's' => 'bold'], $table['columns']);

            foreach ($table['rows'] as $row) {
                $rows[] = $this->excelRow($table['columns'], $row, false);
            }

            if ($table['footer'] !== null) {
                $rows[] = $this->excelRow($table['columns'], $table['footer'], true);
            }

            $widths = [];
            foreach ($table['columns'] as $i => $column) {
                $longest = mb_strlen($column['label']);
                foreach ($table['rows'] as $row) {
                    $longest = max($longest, mb_strlen(self::format($row[$i] ?? '', $column['type'])));
                }
                $widths[] = min(45, max(12, $longest + 2));
            }

            $sheets[] = ['name' => $table['title'], 'widths' => $widths, 'rows' => $rows];
        }

        return $sheets;
    }

    /**
     * How a table cell is shown on screen and in print.
     * A text cell may be ['text' => ..., 'url' => ...] (a link); money, int and percent cells are numbers.
     */
    public static function format(mixed $cell, string $type): string
    {
        if (is_array($cell)) {
            $cell = $cell['text'] ?? '';
        }

        if ($cell === null || $cell === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format((float) $cell, 2),
            'int' => number_format((float) $cell, 0),
            'percent' => number_format((float) $cell, 1).'%',
            default => (string) $cell,
        };
    }

    // ------------------------------------------------------------------
    // Building blocks
    // ------------------------------------------------------------------

    /**
     * What each part of the hotel earned (billed, not money received): rooms, the restaurant and other extras.
     * Charges count on the day they were added to a bill; walk-in bills on the day they were paid.
     *
     * @return array{title: string, columns: list<array{label: string, type: string}>, rows: list<list<mixed>>, footer: list<mixed>|null, empty: string}
     */
    private function departmentTable(string $from, string $to, float $roomRevenue): array
    {
        [$start, $end] = PropertyTime::range($from, $to);

        $charges = FolioCharge::query()
            ->whereNull('voided_at')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->get(['category', 'amount']);

        $walkInLines = WalkInBillLine::query()
            ->whereHas('bill', fn ($bill) => $bill
                ->where('status', WalkInStatus::Paid->value)
                ->where('paid_at', '>=', $start)
                ->where('paid_at', '<', $end))
            ->get(['category', 'amount']);

        $sum = fn (Collection $lines, bool $food): float => round((float) $lines
            ->filter(fn ($line): bool => ($line->category === ChargeCategory::Food) === $food)
            ->sum('amount'), 2);

        $rows = [
            ['Rooms (nights stayed)', round($roomRevenue, 2)],
            ['Restaurant - food and drink on room bills', $sum($charges, true)],
            ['Restaurant - food and drink on walk-in bills', $sum($walkInLines, true)],
            ['Other extras (laundry, transport, other)', round($sum($charges, false) + $sum($walkInLines, false), 2)],
        ];

        return $this->table('Income by department', [
            ['Department', 'text'], ['Billed', 'money'],
        ], $rows, ['Total', round(array_sum(array_column($rows, 1)), 2)]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $columns  [label, type]
     * @param  list<list<mixed>>  $rows
     * @param  list<mixed>|null  $footer
     * @return array{title: string, columns: list<array{label: string, type: string}>, rows: list<list<mixed>>, footer: list<mixed>|null, empty: string}
     */
    private function table(string $title, array $columns, array $rows, ?array $footer = null, string $empty = 'Nothing to show.'): array
    {
        return [
            'title' => $title,
            'columns' => array_map(fn (array $c): array => ['label' => $c[0], 'type' => $c[1]], $columns),
            'rows' => $rows,
            'footer' => $footer,
            'empty' => $empty,
        ];
    }

    /**
     * @param  list<array{label: string, type: string}>  $columns
     * @param  list<mixed>  $row
     * @return list<mixed>
     */
    private function excelRow(array $columns, array $row, bool $bold): array
    {
        $cells = [];

        foreach ($columns as $i => $column) {
            $value = $row[$i] ?? null;

            if (is_array($value)) {
                $value = $value['text'] ?? '';
            }

            $cells[] = match (true) {
                $value === null || $value === '' => null,
                $column['type'] === 'money' => ['v' => (float) $value, 's' => $bold ? 'money_bold' : 'money'],
                $column['type'] === 'int' => ['v' => (int) $value, 's' => $bold ? 'bold' : 'int'],
                $column['type'] === 'percent' => ['v' => (float) $value, 's' => $bold ? 'bold' : 'decimal'],
                default => $bold ? ['v' => (string) $value, 's' => 'bold'] : (string) $value,
            };
        }

        return $cells;
    }

    /** @return array{text: string, url: string}|string */
    private function bookingLink(?Booking $booking): array|string
    {
        return $booking === null ? '' : ['text' => $booking->reference, 'url' => route('staff.folio.show', $booking->id)];
    }

    private function percent(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round(min(100, $part / $whole * 100), 1) : 0.0;
    }

    /**
     * Every night from $from to $to, as Y-m-d strings.
     *
     * @return list<string>
     */
    private function days(string $from, string $to): array
    {
        $days = [];

        for ($d = CarbonImmutable::parse($from); $d->toDateString() <= $to && count($days) < self::MAX_DAYS; $d = $d->addDay()) {
            $days[] = $d->toDateString();
        }

        return $days;
    }

    /**
     * Stays that held a room on at least one night between $from and $to.
     *
     * @return Collection<int, Booking>
     */
    private function stays(string $from, string $to): Collection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value])
            ->whereDate('check_in', '<=', $to)
            ->whereDate('check_out', '>', $from)
            ->get(['id', 'status', 'check_in', 'check_out', 'rate_breakdown'])
            ->filter(fn (Booking $b): bool => $b->check_in->toDateString() <= $to
                && $b->check_out->toDateString() > $from
                && $b->check_in->toDateString() <= $b->check_out->copy()->subDay()->toDateString())
            ->values();
    }

    /**
     * Guests who owe money: everyone in the house, and guests who were allowed to leave with an unpaid balance.
     *
     * @return Collection<int, array{booking: Booking, total: float, paid: float, balance: float}>
     */
    private function unpaidBills(): Collection
    {
        return Booking::query()->with('room')
            ->where(function ($query): void {
                $query->where('status', BookingStatus::CheckedIn->value)
                    ->orWhere(fn ($q) => $q->where('status', BookingStatus::CheckedOut->value)->where('checkout_unpaid_amount', '>', 0));
            })
            ->orderBy('check_out')->orderBy('id')->get()
            ->map(fn (Booking $b): array => [
                'booking' => $b,
                'total' => $b->folioTotal(),
                'paid' => $b->amountPaid(),
                'balance' => $b->balanceDue(),
            ])
            ->filter(fn (array $row): bool => $row['balance'] > 0.004)
            ->values();
    }

    /**
     * All money that came in or went out between two property dates, as one list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function entries(string $from, string $to): Collection
    {
        [$start, $end] = PropertyTime::range($from, $to);
        $entries = collect();

        // Payments and refunds on room bills (section 5.5).
        $payments = Payment::query()->with(['booking', 'creator'])
            ->whereNull('voided_at')
            ->where('paid_at', '>=', $start)->where('paid_at', '<', $end)
            ->get();

        foreach ($payments as $payment) {
            $entries->push($this->entry($payment->paid_at, 'payment', $payment->isRefund() ? 'out' : 'in', $payment->method,
                (float) $payment->amount, $payment->creator, $payment->number, $payment->booking));
        }

        // Deposits taken when the booking was made (FR-08). Hidden (soft-deleted) bookings still count: the money was received.
        $deposits = Booking::withTrashed()->with('creator')
            ->where('deposit_amount', '>', 0)->whereNotNull('deposit_method')
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->get();

        foreach ($deposits as $booking) {
            $entries->push($this->entry($booking->created_at, 'deposit', 'in', $booking->deposit_method,
                (float) $booking->deposit_amount, $booking->creator, (string) ($booking->deposit_reference ?: $booking->reference), $booking));
        }

        // Money paid at check-out (FR-13).
        $checkouts = Booking::withTrashed()->with('checkedOutBy')
            ->where('settlement_amount', '>', 0)->whereNotNull('settlement_method')->whereNotNull('checked_out_at')
            ->where('checked_out_at', '>=', $start)->where('checked_out_at', '<', $end)
            ->get();

        foreach ($checkouts as $booking) {
            $entries->push($this->entry($booking->checked_out_at, 'checkout', 'in', $booking->settlement_method,
                (float) $booking->settlement_amount, $booking->checkedOutBy, (string) ($booking->settlement_reference ?: $booking->reference), $booking));
        }

        // Restaurant walk-in bills that were paid (FR-28). Bills moved to a room bill are paid there instead.
        $walkIns = WalkInBill::query()->with('payer')
            ->where('status', WalkInStatus::Paid->value)->whereNotNull('payment_method')->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)->where('paid_at', '<', $end)
            ->get();

        foreach ($walkIns as $bill) {
            $entries->push($this->entry($bill->paid_at, 'walk_in', 'in', $bill->payment_method,
                (float) $bill->total, $bill->payer, $bill->number, null, $bill->customer_name));
        }

        return $entries->sortBy('at')->values();
    }

    /** @return array<string, mixed> */
    private function entry(CarbonInterface $at, string $source, string $direction, PaymentMethod $method, float $amount, ?User $user, string $ref, ?Booking $booking, ?string $guest = null): array
    {
        return [
            'at' => $at,
            'date' => PropertyTime::dateOf($at),
            'source' => $source,
            'direction' => $direction,
            'method' => $method,
            'amount' => round($amount, 2),
            'user' => $user,
            'user_id' => $user?->id,
            'ref' => $ref,
            'booking_ref' => $booking?->reference,
            'guest' => $guest ?? $booking?->guest_name,
        ];
    }

    /**
     * Money received, refunded and net, in total, by payment method and by source.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array{in: float, out: float, net: float, by_method: array<string, array{in: float, out: float, net: float}>, by_source: array<string, array{in: float, out: float, net: float}>}
     */
    private function money(Collection $entries): array
    {
        $sum = function (Collection $of): array {
            $in = round((float) $of->where('direction', 'in')->sum('amount'), 2);
            $out = round((float) $of->where('direction', 'out')->sum('amount'), 2);

            return ['in' => $in, 'out' => $out, 'net' => round($in - $out, 2)];
        };

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $byMethod[$method->value] = $sum($entries->filter(fn (array $e): bool => $e['method'] === $method));
        }

        $bySource = [];
        foreach (array_keys(self::SOURCES) as $source) {
            $bySource[$source] = $sum($entries->where('source', $source));
        }

        return $sum($entries) + ['by_method' => $byMethod, 'by_source' => $bySource];
    }

    /**
     * @param  array<string, mixed>  $money
     * @return list<list<mixed>>
     */
    private function methodRows(array $money): array
    {
        $rows = [];

        foreach (PaymentMethod::cases() as $method) {
            $m = $money['by_method'][$method->value];
            $rows[] = [$method->label(), $m['in'], $m['out'], $m['net']];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $money
     * @return list<list<mixed>>
     */
    private function sourceRows(array $money): array
    {
        $rows = [];

        foreach (self::SOURCES as $key => $label) {
            $s = $money['by_source'][$key];
            $rows[] = [$label, $s['in'], $s['out'], $s['net']];
        }

        return $rows;
    }
}
