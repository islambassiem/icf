<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionStatusEnum;
use App\Models\ExamType;
use App\Models\ProfileType;
use App\Models\Question;
use App\Models\QuestionArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement_en' => fake()->sentence().'?',
            'statement_ar' => fake()->sentence(),
            'exam_type_id' => ExamType::query()->inRandomOrder()->value('id') ?? ExamType::factory(),
            'question_area_id' => QuestionArea::query()->inRandomOrder()->value('id') ?? QuestionArea::factory(),
            'profile_type_id' => ProfileType::query()->inRandomOrder()->value('id') ?? ProfileType::factory(),
            'status' => fake()->randomElement(QuestionStatusEnum::cases()),
        ];
    }
}
