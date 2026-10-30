<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'provider' => fake()->company(),
            'provider_payment_id' => fake()->uuid(),
            'provider_checkout_id' => fake()->uuid(),
            'amount' => fake()->numberBetween(100, 300),
            'currency' => fake()->randomElement(CurrencyEnum::cases()),
            'status' => fake()->randomElement(PaymentStatusEnum::cases()),
            'paid_at' => fake()->dateTime(),
            'failure_reason' => fake()->optional()->sentence(),
        ];
    }
}
