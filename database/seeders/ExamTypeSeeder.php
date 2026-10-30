<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;

class ExamTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name_en' => 'ACC',
                'name_ar' => 'مساعد مدرب معتمد',
                'description' => null,
            ],
            [
                'name_en' => 'PCC',
                'name_ar' => 'مدرب محترف معتمد',
                'description' => null,
            ],
        ];

        foreach ($types as $type) {
            ExamType::query()->updateOrCreate(['name_en' => $type['name_en']], $type);
        }
    }
}
