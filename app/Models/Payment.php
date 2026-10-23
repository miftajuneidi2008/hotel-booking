<?php

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money received from a guest, or paid back to a guest. Voided, never deleted. */
#[Fillable([
    'number', 'booking_id', 'kind', 'method', 'reference',
    'currency', 'original_amount', 'exchange_rate', 'amount', 'reason',
    'created_by', 'paid_at', 'voided_at', 'voided_by', 'void_reason',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->number ??= static::newNumber();
        });
    }

    /** A short code for receipts and the cash book, e.g. PAY-7H3K9Q. */
    public static function newNumber(): string
    {
        do {
            $number = 'PAY-'.collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))->random(6)->implode('');
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'original_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** Entries that still count (not voided). */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isRefund(): bool
    {
        return $this->kind === PaymentKind::Refund;
    }

    /** ETB effect on what the guest has paid: plus for a payment, minus for a refund. */
    public function signedAmount(): float
    {
        return $this->isRefund() ? -(float) $this->amount : (float) $this->amount;
    }

    public function isForeign(): bool
    {
        return $this->currency !== config('payments.currency', 'ETB');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
