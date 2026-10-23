<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(101, 999);

        return [
            'room_type_id' => RoomType::factory(),
            'number' => (string) $number,
            'floor' => intdiv($number, 100),
            'status' => RoomStatus::Clean,
        ];
    }

    public function dirty(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatus::Dirty]);
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatus::Occupied]);
    }

    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatus::OutOfService]);
    }
}
