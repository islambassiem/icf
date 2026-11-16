<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionType>
 */
class QuestionTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->word(),
            'name_en' => fake()->word(),
            'name_ar' => fake('ar_SA')->word(),
            'mode' => fake()->randomElement(['knowledge', 'situational']),
            'best_points' => $best = fake()->boolean() ? 1 : 0,
            'worst_points' => $best ? 0 : 1,
        ];
    }
}
