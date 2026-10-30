<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProfileType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileType>
 */
class ProfileTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ['name_en' => 'Competence', 'name_ar' => 'الكفاءة'],
            ['name_en' => 'Skill', 'name_ar' => 'المهارة'],
            ['name_en' => 'Value', 'name_ar' => 'القيمة'],
        ];

        return [
            ...fake()->randomElement($types),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
