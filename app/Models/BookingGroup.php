<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['reference', 'name', 'contact_name', 'contact_phone', 'contact_email', 'notes', 'created_by'])]
class BookingGroup extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (BookingGroup $group): void {
            $group->reference ??= static::newReference();
        });
    }

    /** A short code such as GR-4KQ7ZD (letters and digits that are easy to confuse are left out). */
    public static function newReference(): string
    {
        do {
            $reference = 'GR-'.collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))->random(6)->implode('');
        } while (static::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Sum of the rooms that are not cancelled. */
    public function activeTotal(): float
    {
        return round((float) $this->bookings
            ->where('status', '!=', BookingStatus::Cancelled)
            ->sum('total_amount'), 2);
    }

    public function hasConfirmedRooms(): bool
    {
        return $this->bookings->contains(fn (Booking $b): bool => $b->isConfirmed());
    }
}
