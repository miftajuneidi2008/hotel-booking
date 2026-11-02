<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A restaurant table in use by a hotel guest who ordered there. Free again when released or expired. */
#[Fillable(['table_number', 'booking_id', 'created_by', 'released_at', 'released_by'])]
class RoomTableHold extends Model
{
    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    /** Holds that still block the table: not released and not older than the hold time. */
    public function scopeActive(Builder $query): void
    {
        $hours = max(1, (int) config('restaurant.table_hold_hours', 4));

        $query->whereNull('released_at')->where('updated_at', '>=', now()->subHours($hours));
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
}
