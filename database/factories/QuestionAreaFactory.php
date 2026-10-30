<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuestionArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionArea>
 */
class QuestionAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $areas = [
            ['name_en' => 'Knowledge', 'name_ar' => 'المعرفة'],
            ['name_en' => 'Situation', 'name_ar' => 'المواقف'],
        ];

        return [
            ...fake()->randomElement($areas),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
