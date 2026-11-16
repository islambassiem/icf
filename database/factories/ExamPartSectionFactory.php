<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamPart;
use App\Models\ExamPartSection;
use App\Models\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPartSection>
 */
class ExamPartSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_part_id' => ExamPart::factory(),
            'question_type_id' => QuestionType::factory(),
            'question_count' => fake()->numberBetween(18, 22),
            'position' => fake()->numberBetween(1, 5),
        ];
    }
}
