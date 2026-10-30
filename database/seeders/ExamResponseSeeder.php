<?php

namespace Database\Seeders;

use App\Models\ExamAttemptQuestion;
use App\Models\ExamResponse;
use Illuminate\Database\Seeder;

class ExamResponseSeeder extends Seeder
{
    public function run(): void
    {
        $attemptQuestions = ExamAttemptQuestion::with([
            'question.answerOptions',
        ])->get();

        foreach ($attemptQuestions as $attemptQuestion) {

            $options = $attemptQuestion->question?->answerOptions;

            if (! $options || $options->count() < 2) {
                continue;
            }

            $selectedOptions = $options->shuffle()->take(2)->values();

            ExamResponse::query()->updateOrCreate([
                'exam_attempt_question_id' => $attemptQuestion->id,
            ], [
                'best_answer_option_id' => $selectedOptions[0]->id,
                'worst_answer_option_id' => $selectedOptions[1]->id,
                'answered_at' => now(),
            ]);
        }
    }
}
