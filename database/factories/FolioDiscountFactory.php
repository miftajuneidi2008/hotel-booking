<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Booking;
use App\Models\FolioDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FolioDiscount>
 */
class FolioDiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'type' => DiscountType::Fixed,
            'value' => 100,
            'amount' => 100,
            'reason' => 'Test',
        ];
    }
}
