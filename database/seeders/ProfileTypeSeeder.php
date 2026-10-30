<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProfileType;
use Illuminate\Database\Seeder;

class ProfileTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name_en' => 'Competence',
                'name_ar' => 'الكفاءة',
                'description' => null,
            ],
            [
                'name_en' => 'Skill',
                'name_ar' => 'المهارة',
                'description' => null,
            ],
            [
                'name_en' => 'Value',
                'name_ar' => 'القيمة',
                'description' => null,
            ],
        ];

        foreach ($types as $type) {
            ProfileType::query()->updateOrCreate(['name_en' => $type['name_en']], $type);
        }
    }
}
