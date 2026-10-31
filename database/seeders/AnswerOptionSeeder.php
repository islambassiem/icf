<?php

namespace Database\Seeders;

use App\Models\AnswerOption;
use App\Models\Question;
use Illuminate\Database\Seeder;

class AnswerOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Question::query()
            ->chunk(100, function ($questions): void {
                foreach ($questions as $question) {
                    if ($question->answerOptions()->doesntExist()) {
                        $question->answerOptions()->saveMany(
                            AnswerOption::factory()
                                ->count(4)
                                ->make()
                        );
                    }
                }
            });
    }
}
