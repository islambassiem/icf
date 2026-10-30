<?php

namespace Database\Seeders;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExamAttemptSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();
        $subscription = Subscription::query()->first();
        $examType = ExamType::query()->first();

        if (! $user || ! $subscription || ! $examType) {
            return;
        }

        $questions = Question::with('answerOptions')
            ->inRandomOrder()
            ->take(10)
            ->get();

        if ($questions->isEmpty()) {
            return;
        }

        $attempt = ExamAttempt::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'exam_type_id' => $examType->id,
            'status' => 'In progress',
            'started_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        foreach ($questions as $index => $question) {
            $options = $question->answerOptions;

            $snapshot = [
                'statement_en' => $question->statement_en,
                'statement_ar' => $question->statement_ar,
                'answer_options' => $options->map(fn ($option): array => [
                    'id' => $option->id,
                    'statement_en' => $option->statement_en,
                    'statement_ar' => $option->statement_ar,
                    'comment_en' => $option->comment_en,
                    'comment_ar' => $option->comment_ar,
                ])->toArray(),
            ];

            $attemptQuestion = ExamAttemptQuestion::query()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'part_number' => $index < 5 ? 1 : 2,
                'position' => ($index % 5) + 1,
                'question_snapshot' => $snapshot,
            ]);

            if ($options->count() < 2) {
                continue;
            }

            $selectedOptions = $options->random(2);

            $attemptQuestion->response()->create([
                'best_answer_option_id' => $selectedOptions[0]->id,
                'worst_answer_option_id' => $selectedOptions[1]->id,
                'answered_at' => now(),
            ]);
        }
    }
}
