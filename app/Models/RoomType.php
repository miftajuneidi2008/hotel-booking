<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'capacity', 'base_rate'])]
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (RoomType $roomType): void {
            $roomType->slug ??= static::uniqueSlugFor($roomType->name);
        });
    }

    /**
     * A URL slug for the name that no room type (deleted ones included) uses yet.
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'room-type';
        $slug = $base;

        for ($suffix = 2; static::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'base_rate' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<RoomRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }

    /**
     * The nightly rate for the night starting on the given date.
     *
     * The most specific matching rate wins (a seasonal rate beats a weekend
     * rate, which beats the base rate). Ties go to the newest rate.
     */
    public function rateForDate(CarbonInterface $date): string
    {
        $rate = $this->rates
            ->filter(fn (RoomRate $rate): bool => $rate->appliesOn($date))
            ->sort(fn (RoomRate $a, RoomRate $b): int => [$b->specificity(), $b->id] <=> [$a->specificity(), $a->id])
            ->first();

        return $rate?->amount ?? $this->base_rate;
    }
}
