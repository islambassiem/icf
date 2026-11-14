<?php

declare(strict_types=1);

namespace App\Services\Exams;

use App\Exceptions\InsufficientQuestionsException;
use App\Exceptions\NoActiveSubscriptionException;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Starts an exam: checks the subscription, picks the questions and freezes them into the attempt.
 */
class ExamStarter
{
    /**
     * @throws NoActiveSubscriptionException
     * @throws InsufficientQuestionsException
     */
    public function start(User $user, ExamType $type): ExamAttempt
    {
        // 1. Only subscribers can START an exam.
        $subscription = $user->activeSubscription()->first();

        throw_unless($subscription, NoActiveSubscriptionException::class);

        // 2. One exam at a time: continue the one already running.
        $running = $user->examAttempts()->inProgress()->latest('started_at')->first();

        if ($running) {
            return $running;
        }

        $parts = (int) config('portal.exam.parts');
        $perPart = (int) config('portal.exam.questions_per_part');
        $partMinutes = (int) config('portal.exam.minutes_per_part');
        $breakMinutes = (int) config('portal.exam.break_minutes');

        $questionIds = $this->pickQuestions($user, $type, $parts * $perPart);
        $questions = Question::with('answerOptions')->whereIn('id', $questionIds)->get()->keyBy('id');

        return DB::transaction(function () use ($user, $type, $subscription, $questionIds, $questions, $perPart, $partMinutes, $breakMinutes, $parts): ExamAttempt {
            $now = now();

            $attempt = ExamAttempt::query()->create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'exam_type_id' => $type->id,
                'status' => ExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => $now,
                'part_1_ends_at' => $now->copy()->addMinutes($partMinutes),
                // Upper limit if everything is used to the minute; updated when part 2 actually starts.
                'expires_at' => $now->copy()->addMinutes($partMinutes * $parts + $breakMinutes),
            ]);

            foreach ($questionIds->chunk($perPart)->values() as $partIndex => $chunk) {
                foreach ($chunk->values() as $position => $questionId) {
                    ExamAttemptQuestion::query()->create([
                        'attempt_id' => $attempt->id,
                        'question_id' => $questionId,
                        'part_number' => $partIndex + 1,
                        'position' => $position + 1,
                        'question_snapshot' => self::snapshotFor($questions[$questionId]),
                    ]);
                }
            }

            return $attempt;
        });
    }

    /**
     * Questions are never repeated inside an exam, and a user sees questions they have not seen before
     * first. Only when the unseen pool runs out are older ones used again (the longest ago first).
     *
     * @return Collection<int, int>
     *
     * @throws InsufficientQuestionsException
     */
    private function pickQuestions(User $user, ExamType $type, int $needed): Collection
    {
        $eligible = $this->eligibleIds($type);

        if ($eligible->count() < $needed) {
            throw new InsufficientQuestionsException($eligible->count(), $needed);
        }

        // question_id => when this user last got it
        $seen = DB::table('exam_attempt_questions as aq')
            ->join('exam_attempts as a', 'a.id', '=', 'aq.attempt_id')
            ->where('a.user_id', $user->id)
            ->groupBy('aq.question_id')
            ->selectRaw('aq.question_id, MAX(a.started_at) as last_seen')
            ->pluck('last_seen', 'question_id');

        $picked = $eligible->reject(fn (int $id) => $seen->has($id))->shuffle()->take($needed)->values();

        if ($picked->count() < $needed) {
            $again = $eligible->filter(fn (int $id) => $seen->has($id))
                ->shuffle()                                   // random order among equals...
                ->sortBy(fn (int $id): mixed => $seen[$id])          // ...then the longest-ago first
                ->take($needed - $picked->count())
                ->values();

            $picked = $picked->merge($again);
        }

        return $picked->shuffle()->values();
    }

    /**
     * Questions that can be used in an exam: right type, right status, and a valid best and worst answer.
     *
     * @return Collection<int, int>
     */
    private function eligibleIds(ExamType $type): Collection
    {
        return Question::query()
            ->where('exam_type_id', $type->id)
            ->when(config('portal.question_status'), fn ($q, $status) => $q->where('status', $status))
            ->whereHas('answerOptions', fn ($q) => $q->where('is_best', true))
            ->whereHas('answerOptions', fn ($q) => $q->where('is_worst', true))
            ->has('answerOptions', '>=', 2)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * Everything the exam needs, frozen at start (so later edits to the question bank do not change
     * an attempt). Contains the correct answers: never send this array to the browser.
     *
     * @return array<string, mixed>
     */
    public static function snapshotFor(Question $question): array
    {
        return [
            'statement_en' => $question->statement_en,
            'statement_ar' => $question->statement_ar,
            'question_area_id' => $question->question_area_id,
            'profile_type_id' => $question->profile_type_id,
            // Options are shuffled once per attempt and keep that order.
            'options' => $question->answerOptions->shuffle()->values()->map(fn ($o): array => [
                'id' => $o->id,
                'statement_en' => $o->statement_en,
                'statement_ar' => $o->statement_ar,
                'comment_en' => $o->comment_en,
                'comment_ar' => $o->comment_ar,
                'is_best' => (bool) $o->is_best,
                'is_worst' => (bool) $o->is_worst,
            ])->all(),
        ];
    }
}
