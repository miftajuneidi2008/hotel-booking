<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Room',
            'description' => fake()->sentence(),
            'capacity' => fake()->numberBetween(1, 6),
            'base_rate' => fake()->numberBetween(1000, 6000),
        ];
    }
}
