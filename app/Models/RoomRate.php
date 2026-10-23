<?php

namespace App\Models;

use App\Enums\RateDays;
use Carbon\CarbonInterface;
use Database\Factories\RoomRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'amount', 'applies_on', 'starts_on', 'ends_on'])]
class RoomRate extends Model
{
    /** @use HasFactory<RoomRateFactory> */
    use HasFactory;

    /**
     * Nights counted as "weekend" nights (Friday and Saturday night).
     *
     * @var list<int>
     */
    public const WEEKEND_DAYS = [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applies_on' => RateDays::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function isSeasonal(): bool
    {
        return $this->starts_on !== null || $this->ends_on !== null;
    }

    public function appliesOn(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        if ($this->starts_on !== null && $day < $this->starts_on->toDateString()) {
            return false;
        }

        if ($this->ends_on !== null && $day > $this->ends_on->toDateString()) {
            return false;
        }

        return $this->applies_on !== RateDays::Weekends
            || in_array($date->dayOfWeek, self::WEEKEND_DAYS, true);
    }

    /**
     * Higher means more specific: seasonal beats weekend beats every-night.
     */
    public function specificity(): int
    {
        return ($this->isSeasonal() ? 2 : 0) + ($this->applies_on === RateDays::Weekends ? 1 : 0);
    }
}
