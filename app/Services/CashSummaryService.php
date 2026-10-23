<?php

namespace App\Services;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\WalkInStatus;
use App\Exceptions\BookingException;
use App\Models\CashClosing;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalkInBill;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashSummaryService
{
    public function timezone(): string
    {
        return (string) config('payments.timezone', config('app.timezone'));
    }

    /** Today's date at the property. */
    public function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    /**
     * @return array{
     *     date: string,
     *     rows: Collection<int, array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     payments: Collection<int, Payment>,
     *     voided: Collection<int, Payment>
     * }
     */
    public function day(string $date, ?int $userId = null): array
    {
        [$from, $to] = $this->range($date);

        $payments = Payment::query()
            ->with(['booking', 'creator'])
            ->whereNull('voided_at')
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $to)
            ->when($userId, fn ($query) => $query->where('created_by', $userId))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        // Entries voided on this day (they were removed from the totals, so they are listed for the closing check).
        $voided = Payment::query()
            ->with(['booking', 'creator', 'voider'])
            ->whereNotNull('voided_at')
            ->where('voided_at', '>=', $from)
            ->where('voided_at', '<', $to)
            ->when($userId, fn ($query) => $query->where('created_by', $userId))
            ->orderBy('voided_at')
            ->get();

        // Restaurant walk-in bills paid on this day. The money went into the same drawer, so it must be in the
        // closing figures. A bill moved to a room bill is paid there (as a payment above), so it is not counted twice.
        $walkIns = WalkInBill::query()
            ->with('payer')
            ->where('status', WalkInStatus::Paid->value)
            ->whereNotNull('payment_method')
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $to)
            ->when($userId, fn ($query) => $query->where('paid_by', $userId))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $counted = $payments->concat($walkIns->map(fn (WalkInBill $bill): Payment => $this->asPayment($bill)));

        $rows = $counted
            ->groupBy(fn (Payment $payment): int => (int) $payment->created_by)
            ->map(fn (Collection $entries): array => $this->row($entries))
            ->sortBy(fn (array $row): string => strtolower((string) ($row['user']?->name ?? '')))
            ->values();

        return [
            'date' => $date,
            'rows' => $rows,
            'totals' => $this->row($counted, withUser: false),
            // The front desk and the restaurant keep separate drawers, so their cash is also shown apart.
            'by_outlet' => [
                'front_desk' => $this->row($payments, withUser: false),
                'restaurant' => $this->row($walkIns->map(fn (WalkInBill $bill): Payment => $this->asPayment($bill)), withUser: false),
            ],
            'payments' => $payments,
            'walk_ins' => $walkIns,
            'voided' => $voided,
        ];
    }

    /** The closing a person made for a day, if any. */
    public function closingFor(User $user, string $date): ?CashClosing
    {
        return CashClosing::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->first();
    }

    /**
     * Everybody's closings of a day (or one person's), for the manager to check differences.
     *
     * @return Collection<int, CashClosing>
     */
    public function closings(string $date, ?int $userId = null): Collection
    {
        return CashClosing::query()
            ->with('user')
            ->whereDate('business_date', $date)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('closed_at')
            ->get();
    }

    /**
     * Close a person's day: store the cash the system expected, the cash they counted and the difference.
     * Birr cash only; foreign cash is handed over as listed on the summary.
     *
     * @throws BookingException
     */
    public function close(User $user, string $date, float $counted, ?string $note): CashClosing
    {
        if ($date > $this->today()) {
            throw new BookingException(__('A day that has not started yet cannot be closed.'));
        }

        try {
            return DB::transaction(function () use ($user, $date, $counted, $note): CashClosing {
                $already = CashClosing::query()
                    ->where('user_id', $user->id)
                    ->whereDate('business_date', $date)
                    ->lockForUpdate()
                    ->exists();

                if ($already) {
                    throw new BookingException(__('You already closed this day.'));
                }

                $expected = (float) $this->day($date, $user->id)['totals']['cash_net'];
                $counted = round($counted, 2);

                return CashClosing::create([
                    'user_id' => $user->id,
                    'business_date' => $date,
                    'expected_cash' => round($expected, 2),
                    'counted_cash' => $counted,
                    'difference' => round($counted - $expected, 2),
                    'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                    'closed_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BookingException(__('You already closed this day.'));
        }
    }

    /** A paid walk-in bill as an (unsaved) birr payment, so it is added up like every other payment. */
    private function asPayment(WalkInBill $bill): Payment
    {
        $payment = new Payment([
            'number' => $bill->number,
            'kind' => PaymentKind::Payment,
            'method' => $bill->payment_method,
            'reference' => $bill->payment_reference,
            'currency' => (string) config('payments.currency', 'ETB'),
            'original_amount' => $bill->total,
            'exchange_rate' => 1,
            'amount' => $bill->total,
            'created_by' => $bill->paid_by,
            'paid_at' => $bill->paid_at,
        ]);

        return $payment->setRelation('creator', $bill->payer);
    }

    /**
     * Staff who may appear in the summary: everyone except guest accounts.
     *
     * @return Collection<int, User>
     */
    public function staff(): Collection
    {
        return User::query()->where('role', '!=', 'guest')->orderBy('name')->get();
    }

    /**
     * Start and end of the day, converted to the app timezone because that is how timestamps are stored.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $date): array
    {
        $start = CarbonImmutable::parse($date, $this->timezone())->startOfDay();
        $appTimezone = (string) config('app.timezone');

        return [$start->setTimezone($appTimezone), $start->addDay()->setTimezone($appTimezone)];
    }

    /**
     * @param  Collection<int, Payment>  $entries
     * @return array<string, mixed>
     */
    private function row(Collection $entries, bool $withUser = true): array
    {
        $byMethod = [];

        foreach (PaymentMethod::cases() as $method) {
            $of = $entries->where('method', $method);

            $in = round((float) $of->filter(fn (Payment $p): bool => ! $p->isRefund())->sum('amount'), 2);
            $out = round((float) $of->filter(fn (Payment $p): bool => $p->isRefund())->sum('amount'), 2);

            $byMethod[$method->value] = ['method' => $method, 'in' => $in, 'out' => $out, 'net' => round($in - $out, 2)];
        }

        $in = round((float) $entries->filter(fn (Payment $p): bool => ! $p->isRefund())->sum('amount'), 2);
        $out = round((float) $entries->filter(fn (Payment $p): bool => $p->isRefund())->sum('amount'), 2);

        // Notes and coins of other currencies that have to be handed over with the cash.
        $foreignCash = [];

        foreach ($entries->where('method', PaymentMethod::Cash)->filter(fn (Payment $p): bool => $p->isForeign())->groupBy('currency') as $currency => $of) {
            $foreignCash[$currency] = round((float) $of->sum(fn (Payment $p): float => $p->isRefund() ? -(float) $p->original_amount : (float) $p->original_amount), 2);
        }

        return [
            'user' => $withUser ? $entries->first()?->creator : null,
            'count' => $entries->count(),
            'by_method' => $byMethod,
            'in' => $in,
            'out' => $out,
            'net' => round($in - $out, 2),
            // Cash that should be in the drawer: birr value of cash taken minus cash paid back.
            'cash_net' => $byMethod[PaymentMethod::Cash->value]['net'],
            'foreign_cash' => $foreignCash,
        ];
    }
}
