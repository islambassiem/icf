<?php

namespace Database\Seeders;

use App\Models\ExamAttempt;
use App\Models\ExamResponse;
use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamAttemptQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = Question::with('answerOptions')->get();

        if ($questions->isEmpty()) {
            return;
        }

        $attempts = ExamAttempt::all();

        foreach ($attempts as $attempt) {
            DB::transaction(function () use ($attempt, $questions): void {

                $attemptQuestionIds = $attempt->attemptQuestions()
                    ->pluck('id');

                ExamResponse::query()->whereIn('exam_attempt_question_id', $attemptQuestionIds)->delete();

                $attempt->attemptQuestions()->delete();

                $selectedQuestions = $questions
                    ->shuffle()
                    ->take(10)
                    ->values();

                foreach ($selectedQuestions as $index => $question) {

                    $snapshot = [
                        'statement_en' => $question->statement_en,
                        'statement_ar' => $question->statement_ar,

                        'answer_options' => $question->answerOptions
                            ->map(fn ($option): array => [
                                'id' => $option->id,
                                'statement_en' => $option->statement_en,
                                'statement_ar' => $option->statement_ar,
                                'comment_en' => $option->comment_en,
                                'comment_ar' => $option->comment_ar,
                            ])
                            ->toArray(),
                    ];

                    $attempt->attemptQuestions()->create([
                        'question_id' => $question->id,
                        'part_number' => $index < 5 ? 1 : 2,
                        'position' => ($index % 5) + 1,
                        'question_snapshot' => $snapshot,
                    ]);
                }
            });
        }
    }
}
