<?php

namespace App\Models;

use App\Enums\ChargeCategory;
use Database\Factories\FolioChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra charge on a guest bill (food, laundry, transport, other).
 * Never deleted: a wrong charge is voided with a reason.
 */
#[Fillable([
    'booking_id', 'split_from_id', 'category', 'description', 'quantity', 'unit_price', 'amount', 'created_by',
    'voided_at', 'voided_by', 'void_reason', 'void_was_split', 'void_approved_by',
])]
class FolioCharge extends Model
{
    /** @use HasFactory<FolioChargeFactory> */
    use HasFactory;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'category' => ChargeCategory::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
            'void_was_split' => 'boolean',
        ];
    }

    /** Charges that still count on the bill. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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

    /** @return BelongsTo<User, $this> */
    public function voidApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_approved_by');
    }
}
