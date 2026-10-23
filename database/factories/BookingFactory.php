<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = today()->addDays(fake()->numberBetween(1, 30));
        $checkOut = $checkIn->copy()->addDays(fake()->numberBetween(1, 5));

        return [
            'room_id' => Room::factory(),
            'source' => BookingSource::WalkIn,
            'status' => BookingStatus::Confirmed,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'guests' => 1,
            'guest_name' => fake()->name(),
            'guest_phone' => '+251911000000',
            'total_amount' => 1800,
            'rate_breakdown' => [$checkIn->toDateString() => 1800],
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Test',
        ]);
    }
}
