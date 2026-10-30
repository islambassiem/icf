<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamType>
 */
class ExamTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ['name_en' => 'ACC', 'name_ar' => 'مساعد مدرب معتمد'],
            ['name_en' => 'PCC', 'name_ar' => 'مدرب محترف معتمد'],
        ];

        return [
            ...fake()->randomElement($types),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
