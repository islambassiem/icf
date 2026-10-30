<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\QuestionArea;
use Illuminate\Database\Seeder;

class QuestionAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = [
            [
                'name_en' => 'Knowledge',
                'name_ar' => 'المعرفة',
                'description' => null,
            ],
            [
                'name_en' => 'Situation',
                'name_ar' => 'المواقف',
                'description' => null,
            ],
        ];

        foreach ($areas as $area) {
            QuestionArea::query()->updateOrCreate(['name_en' => $area['name_en']], $area);
        }
    }
}
