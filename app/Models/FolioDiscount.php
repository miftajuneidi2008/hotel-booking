<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\FolioDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A discount on a guest bill. Fixed in ETB when applied; reversed, never deleted. */
#[Fillable([
    'booking_id', 'type', 'value', 'amount', 'reason', 'needs_approval', 'approved_by', 'created_by',
    'voided_at', 'voided_by', 'void_reason', 'void_approved_by',
])]
class FolioDiscount extends Model
{
    /** @use HasFactory<FolioDiscountFactory> */
    use HasFactory;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'amount' => 'decimal:2',
            'needs_approval' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    /** Discounts that still count on the bill. */
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
