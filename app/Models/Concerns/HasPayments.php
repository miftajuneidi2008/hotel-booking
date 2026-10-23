<?php

namespace App\Models\Concerns;

use App\Enums\PaymentKind;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payments and refunds on a booking (section 5.5).
 *
 * Deposits and check-out payments recorded before this section stay in the bookings table;
 * Booking::amountPaid() adds them to the net of the payments table.
 */
trait HasPayments
{
    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Payments minus refunds, in ETB. Voided entries are left out. */
    public function paymentsNet(): float
    {
        if (! $this->exists) {
            return 0.0;
        }

        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->whereNull('voided_at')
                ->sum(fn (Payment $payment): float => $payment->signedAmount()), 2);
        }

        $net = $this->payments()
            ->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN kind = ? THEN -amount ELSE amount END), 0) as net', [PaymentKind::Refund->value])
            ->value('net');

        return round((float) $net, 2);
    }
}
