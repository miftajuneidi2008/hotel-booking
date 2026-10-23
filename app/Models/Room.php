<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['room_type_id', 'number', 'floor', 'status'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'clean',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'status' => RoomStatus::class,
        ];
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Rooms that can be sold for the stay [$checkIn, $checkOut): not out of
     * service and not held by another confirmed or checked-in booking.
     *
     * @param  Builder<Room>  $query
     */
    public function scopeAvailableBetween(Builder $query, string $checkIn, string $checkOut): void
    {
        $query->where('status', '!=', RoomStatus::OutOfService->value)
            ->whereDoesntHave('bookings', fn (Builder $bookings) => $bookings
                ->blocking()
                ->overlapping($checkIn, $checkOut));
    }

    /**
     * @return HasMany<RoomPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(RoomPhoto::class);
    }
}
