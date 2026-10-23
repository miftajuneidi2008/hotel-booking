<?php

namespace Database\Factories;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'kind' => PaymentKind::Payment,
            'method' => PaymentMethod::Cash,
            'currency' => 'ETB',
            'original_amount' => 500,
            'exchange_rate' => 1,
            'amount' => 500,
            'created_by' => User::factory()->receptionist(),
            'paid_at' => now(),
        ];
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => PaymentKind::Refund, 'reason' => 'Test refund']);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => ['voided_at' => now(), 'void_reason' => 'Test void']);
    }
}
