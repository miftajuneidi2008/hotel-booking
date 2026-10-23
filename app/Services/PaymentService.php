<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Payments, part payments and refunds on a guest bill (section 5.5).
 *
 * Every change locks the booking inside a transaction, like FolioService and StayService, so
 * two people at the desk cannot both take the last part of the same balance. Entries are never
 * edited or deleted: a wrong one is voided with a reason and the name of the user (rules 3, 6, 7).
 */
class PaymentService
{
    /** @return list<string> ETB first, then the foreign currencies the property accepts. */
    public function currencies(): array
    {
        return array_values(array_unique([
            (string) config('payments.currency', 'ETB'),
            ...array_map('strtoupper', (array) config('payments.foreign_currencies', [])),
        ]));
    }

    /**
     * Record money received (FR-20, FR-21, FR-22). Part payments are fine; paying more than
     * the balance is not.
     *
     * @param  array{amount: float|int|string, method: string, reference?: ?string, currency?: ?string, exchange_rate?: float|int|string|null}  $data
     *
     * @throws BookingException
     */
    public function record(Booking $booking, array $data, User $by): Payment
    {
        return DB::transaction(function () use ($booking, $data, $by): Payment {
            $fresh = $this->lock($booking->id);

            if ($fresh->status === BookingStatus::Cancelled) {
                throw new BookingException('This booking is cancelled, so it cannot take payments. If money was received, record a refund.');
            }

            $money = $this->money($data);
            $method = $this->method($data);
            $reference = $this->reference($method, $data['reference'] ?? null);

            $balance = $fresh->balanceDue();

            if ($money['amount'] > $balance + 0.004) {
                throw new BookingException('The payment (ETB '.number_format($money['amount'], 2)
                    .') is more than the balance due (ETB '.number_format(max($balance, 0), 2).').');
            }

            return $fresh->payments()->create([
                'kind' => PaymentKind::Payment,
                'method' => $method,
                'reference' => $reference,
                'currency' => $money['currency'],
                'original_amount' => $money['original'],
                'exchange_rate' => $money['rate'],
                'amount' => $money['amount'],
                'created_by' => $by->id,
                'paid_at' => now(),
            ]);
        });
    }

    /**
     * Pay money back to the guest (FR-21). It needs a reason, it cannot be more than the guest has
     * paid, and a refund over the owner's limit needs a manager or the owner.
     *
     * @param  array{amount: float|int|string, method: string, reference?: ?string, reason: string, currency?: ?string, exchange_rate?: float|int|string|null}  $data
     *
     * @throws BookingException
     */
    public function refund(Booking $booking, array $data, User $by): Payment
    {
        return DB::transaction(function () use ($booking, $data, $by): Payment {
            $fresh = $this->lock($booking->id);

            $reason = trim((string) ($data['reason'] ?? ''));

            if ($reason === '') {
                throw new BookingException('Enter the reason for the refund.');
            }

            $money = $this->money($data);
            $method = $this->method($data);
            $reference = $this->reference($method, $data['reference'] ?? null);

            $paid = $fresh->amountPaid();

            if ($money['amount'] > $paid + 0.004) {
                throw new BookingException('The refund (ETB '.number_format($money['amount'], 2)
                    .') is more than the guest has paid (ETB '.number_format(max($paid, 0), 2).').');
            }

            $limit = config('payments.refund_approval_amount');

            if ($limit !== null && $money['amount'] > (float) $limit && ! $by->canApproveCheckout()) {
                throw new BookingException('A refund of more than ETB '.number_format((float) $limit, 2).' needs a manager or the owner.');
            }

            return $fresh->payments()->create([
                'kind' => PaymentKind::Refund,
                'method' => $method,
                'reference' => $reference,
                'currency' => $money['currency'],
                'original_amount' => $money['original'],
                'exchange_rate' => $money['rate'],
                'amount' => $money['amount'],
                'reason' => $reason,
                'created_by' => $by->id,
                'paid_at' => now(),
            ]);
        });
    }

    /**
     * Cancel a wrong entry. Only a manager or the owner can do it, with a reason. The entry stays
     * in the books, marked as voided.
     *
     * @throws BookingException
     */
    public function void(Payment $payment, string $reason, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $by): Payment {
            $booking = $this->lock($payment->booking_id);
            $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $by->canManageProperty()) {
                throw new BookingException('Only a manager or the owner can void a payment.');
            }

            if ($fresh->isVoided()) {
                throw new BookingException('This entry is already voided.');
            }

            $reason = trim($reason);

            if ($reason === '') {
                throw new BookingException('Enter the reason for voiding this entry.');
            }

            // Voiding a refund puts the money back on the bill, which must not go over what is owed.
            if ($fresh->isRefund() && $booking->amountPaid() + (float) $fresh->amount > $booking->folioTotal() + 0.004) {
                throw new BookingException('Voiding this refund would leave the guest having paid more than the bill. Remove the charge or discount first.');
            }

            $fresh->update([
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * What was handed over and what it is worth in ETB (FR-22).
     *
     * @param  array<string, mixed>  $data
     * @return array{currency: string, original: float, rate: float, amount: float}
     *
     * @throws BookingException
     */
    private function money(array $data): array
    {
        $base = (string) config('payments.currency', 'ETB');
        $currency = strtoupper((string) ($data['currency'] ?? '')) ?: $base;
        $original = round((float) ($data['amount'] ?? 0), 2);

        if (! in_array($currency, $this->currencies(), true)) {
            throw new BookingException("This property does not accept {$currency}.");
        }

        if ($original <= 0) {
            throw new BookingException('The amount must be more than zero.');
        }

        $rate = 1.0;

        if ($currency !== $base) {
            $rate = round((float) ($data['exchange_rate'] ?? 0), 4);

            if ($rate <= 0) {
                throw new BookingException("Enter the exchange rate: how many {$base} for 1 {$currency}.");
            }
        }

        $amount = round($original * $rate, 2);

        if ($amount < 0.01) {
            throw new BookingException('The amount is too small.');
        }

        return ['currency' => $currency, 'original' => $original, 'rate' => $rate, 'amount' => $amount];
    }

    /** @param array<string, mixed> $data */
    private function method(array $data): PaymentMethod
    {
        $method = PaymentMethod::tryFrom((string) ($data['method'] ?? ''));

        if ($method === null) {
            throw new BookingException('Choose how it was paid.');
        }

        return $method;
    }

    /** Cash needs no reference; a bank transfer, Telebirr or card payment must have one (FR-20). */
    private function reference(PaymentMethod $method, ?string $reference): ?string
    {
        $reference = trim((string) $reference);

        if ($reference === '' && $method !== PaymentMethod::Cash) {
            throw new BookingException('Enter the reference number for a '.strtolower($method->label()).' payment.');
        }

        return $reference === '' ? null : $reference;
    }

    private function lock(int $bookingId): Booking
    {
        return Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
    }
}
