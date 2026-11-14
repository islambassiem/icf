<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // UserSeeder::class,
            // SubscriptionPlanSeeder::class,
            // ExamTypeSeeder::class,
            // SubscriptionSeeder::class,
            // PaymentSeeder::class,
            // QuestionAreaSeeder::class,
            // ProfileTypeSeeder::class,
            // QuestionSeeder::class,
            // ExamAttemptSeeder::class,
            // ExamAttemptQuestionSeeder::class,
            // ExamResponseSeeder::class,
            PortalDemoSeeder::class,
        ]);
    }
}
