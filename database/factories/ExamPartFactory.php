<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamPart;
use App\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPart>
 */
class ExamPartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_type_id' => ExamType::factory(),
            'part_number' => fake()->numberBetween(1, 2),
            'minutes' => fake()->numberBetween(30, 60),
            'break_minutes' => fake()->numberBetween(5, 10),
        ];
    }
}
