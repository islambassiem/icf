<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamAttemptStatusEnum;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
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
            'subscription_id' => Subscription::factory(),
            'exam_type_id' => ExamType::factory(),
            'status' => fake()->randomElement(ExamAttemptStatusEnum::cases()),
            'started_at' => $startedAt = fake()->dateTimeBetween('-30 days', 'now'),
            'part_1_ends_at' => null,
            'break_started_at' => null,
            'part_2_started_at' => null,
            'expires_at' => fake()->dateTimeBetween(
                $startedAt,
                (clone $startedAt)->modify('+3 hours')
            ),
            'completed_at' => null,
            'score' => null,
        ];
    }
}
