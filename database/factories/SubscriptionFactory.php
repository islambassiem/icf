<?php

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => fake()->randomElement(SubscriptionStatusEnum::cases()),
            'starts_at' => $startDate = Date::parse(fake()->dateTime()),
            'ends_at' => $startDate->addWeeks(2),
            'amount_paid' => fake()->numberBetween(100, 300),
            'currency' => fake()->randomElement(CurrencyEnum::cases()),
        ];
    }
}
