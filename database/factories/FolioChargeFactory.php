<?php

namespace Database\Factories;

use App\Enums\ChargeCategory;
use App\Models\Booking;
use App\Models\FolioCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FolioCharge>
 */
class FolioChargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $price = fake()->numberBetween(50, 400);

        return [
            'booking_id' => Booking::factory(),
            'category' => ChargeCategory::Food,
            'description' => fake()->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $price,
            'amount' => $quantity * $price,
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'voided_at' => now(),
            'void_reason' => 'Test',
        ]);
    }
}
