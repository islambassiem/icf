<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnswerOption;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResponse>
 */
class ExamResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_attempt_question_id' => ExamAttemptQuestion::factory(),
            'best_answer_option_id' => AnswerOption::factory(),
            'worst_answer_option_id' => null,
            'answered_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
