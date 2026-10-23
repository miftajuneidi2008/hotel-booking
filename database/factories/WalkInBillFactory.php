<?php

namespace Database\Factories;

use App\Enums\WalkInStatus;
use App\Models\WalkInBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalkInBill>
 */
class WalkInBillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => WalkInStatus::Open,
            'total' => 0,
        ];
    }
}
