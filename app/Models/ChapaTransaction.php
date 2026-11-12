<?php

namespace App\Models;

use App\Enums\ChapaStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt to pay a booking through Chapa. */
#[Fillable([
    'booking_id', 'user_id', 'payment_id', 'tx_ref', 'amount', 'currency', 'status',
    'chapa_reference', 'channel', 'checkout_url', 'note', 'response', 'paid_at',
])]
class ChapaTransaction extends Model
{
    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => ChapaStatus::class,
            'amount' => 'decimal:2',
            'response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
