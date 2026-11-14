<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Question::factory()
            ->acc()
            ->count(800)
            ->hasAnswerOptions(4)
            ->create();

        Question::factory()
            ->pcc()
            ->count(800)
            ->hasAnswerOptions(4)
            ->create();
    }
}
