<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\WalkInStatus;
use Database\Factories\WalkInBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bill for a restaurant or bar customer who is not staying in the hotel (FR-28). */
#[Fillable([
    'number', 'customer_name', 'status', 'total',
    'payment_method', 'payment_reference', 'paid_at', 'paid_by',
    'booking_id', 'transferred_at', 'transferred_by',
    'voided_at', 'voided_by', 'void_reason', 'created_by',
])]
class WalkInBill extends Model
{
    /** @use HasFactory<WalkInBillFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WalkInBill $bill): void {
            $bill->number ??= static::newNumber();
        });
    }

    /** A short code for the receipt, e.g. WI-7H3K9Q. */
    public static function newNumber(): string
    {
        do {
            $number = 'WI-'.collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))->random(6)->implode('');
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => WalkInStatus::class,
            'total' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'transferred_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === WalkInStatus::Open;
    }

    /** @return HasMany<WalkInBillLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(WalkInBillLine::class);
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
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** @return BelongsTo<User, $this> */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
