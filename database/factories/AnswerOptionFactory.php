<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnswerOption;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnswerOption>
 */
class AnswerOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'statement_en' => fake()->sentence(),
            'statement_ar' => fake('ar_SA')->sentence(),
            'comment_en' => fake()->sentence(),
            'comment_ar' => fake('ar_SA')->sentence(),
            'is_best' => fake()->boolean(),
            'is_worst' => fn (array $attributes): bool => ! $attributes['is_best'],
        ];
    }
}
