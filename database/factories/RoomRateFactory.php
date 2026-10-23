<?php

namespace Database\Factories;

use App\Enums\RateDays;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomRate>
 */
class RoomRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'name' => fake()->unique()->words(2, true),
            'amount' => fake()->numberBetween(1000, 6000),
            'applies_on' => RateDays::AllDays,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }

    public function weekend(): static
    {
        return $this->state(fn (array $attributes) => ['applies_on' => RateDays::Weekends]);
    }

    public function seasonal(string $startsOn, string $endsOn): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }
}
