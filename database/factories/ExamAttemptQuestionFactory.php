<?php

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttemptQuestion>
 */
class ExamAttemptQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = Question::factory()->create();

        return [
            'attempt_id' => ExamAttempt::factory(),
            'question_id' => $question->id,

            'part_number' => fake()->randomElement([1, 2]),
            'position' => fake()->numberBetween(1, 50),

            'question_snapshot' => [
                'statement_en' => $question->statement_en,
                'statement_ar' => $question->statement_ar,
                'answer_options' => $question->answerOptions()
                    ->get()
                    ->map(fn ($option): array => [
                        'id' => $option->id,
                        'statement_en' => $option->statement_en,
                        'statement_ar' => $option->statement_ar,
                        'comment_en' => $option->comment_en,
                        'comment_ar' => $option->comment_ar,
                    ])
                    ->all(),
            ],
        ];
    }
}
