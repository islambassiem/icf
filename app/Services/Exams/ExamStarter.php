<?php

declare(strict_types=1);

namespace App\Services\Exams;

use App\Exceptions\ExamNotConfiguredException;
use App\Exceptions\InsufficientQuestionsException;
use App\Exceptions\NoActiveSubscriptionException;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Starts an exam: checks the subscription, reads the exam structure from the database,
 * picks the questions for every section and freezes them into the attempt.
 */
class ExamStarter
{
    /**
     * @throws NoActiveSubscriptionException
     * @throws ExamNotConfiguredException
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

        // 3. The structure (parts, minutes, sections, points) comes from the database.
        $blueprint = ExamBlueprint::for($type);

        // 4. Pick the questions: part by part, section by section.
        $picked = collect();   // every question id chosen so far: nothing is used twice in one exam
        $plan = [];            // [partIndex => [[questionTypeId, [ids...]], ...]]

        foreach ($blueprint['parts'] as $partIndex => $part) {
            foreach ($part['sections'] as $section) {
                $ids = $this->pickQuestions($user, $type, $section['type_id'], $section['mode'], $section['count'], $picked, $section['name_en']);

                $picked = $picked->merge($ids);
                $plan[$partIndex][] = $ids;
            }
        }

        $questions = Question::with(['answerOptions', 'questionType'])->whereIn('id', $picked)->get()->keyBy('id');

        return DB::transaction(function () use ($user, $type, $subscription, $blueprint, $plan, $questions): ExamAttempt {
            $now = now();
            $first = $blueprint['parts'][0];
            $second = $blueprint['parts'][1];

            $attempt = ExamAttempt::query()->create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'exam_type_id' => $type->id,
                'status' => ExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => $now,
                'part_1_ends_at' => $now->copy()->addMinutes($first['minutes']),
                // Upper limit if everything is used to the minute; updated when part 2 actually starts.
                'expires_at' => $now->copy()->addMinutes($first['minutes'] + $first['break_minutes'] + $second['minutes']),
                'structure_snapshot' => ExamBlueprint::snapshot($blueprint),
            ]);

            foreach ($plan as $partIndex => $sections) {
                $position = 0;

                foreach ($sections as $ids) {
                    foreach ($ids as $questionId) {
                        ExamAttemptQuestion::query()->create([
                            'attempt_id' => $attempt->id,
                            'question_id' => $questionId,
                            'part_number' => $partIndex + 1,
                            'position' => ++$position,
                            'question_snapshot' => self::snapshotFor($questions[$questionId]),
                        ]);
                    }
                }
            }

            return $attempt;
        });
    }

    /**
     * Questions the user has not seen before come first; only when fewer remain are older ones used
     * again, the longest ago first. Never anything already chosen for this exam.
     *
     * @param  Collection<int, int>  $alreadyPicked
     * @return Collection<int, int>
     *
     * @throws InsufficientQuestionsException
     */
    private function pickQuestions(User $user, ExamType $type, int $questionTypeId, string $mode, int $count, Collection $alreadyPicked, string $label): Collection
    {
        $eligible = $this->eligibleIds($type, $questionTypeId, $mode)->reject(fn (int $id) => $alreadyPicked->contains($id))->values();

        if ($eligible->count() < $count) {
            throw new InsufficientQuestionsException($eligible->count(), $count, $label);
        }

        // question_id => when this user last got it
        $seen = DB::table('exam_attempt_questions as aq')
            ->join('exam_attempts as a', 'a.id', '=', 'aq.attempt_id')
            ->where('a.user_id', $user->id)
            ->groupBy('aq.question_id')
            ->selectRaw('aq.question_id, MAX(a.started_at) as last_seen')
            ->pluck('last_seen', 'question_id');

        $unseen = $eligible->reject(fn (int $id) => $seen->has($id))->shuffle()->take($count)->values();

        if ($unseen->count() >= $count) {
            return $unseen;
        }

        $again = $eligible->filter(fn (int $id) => $seen->has($id))
            ->shuffle()                                   // random among equals...
            ->sortBy(fn (int $id): mixed => $seen[$id])          // ...then the longest-ago first
            ->take($count - $unseen->count())
            ->values();

        return $unseen->merge($again)->values();
    }

    /**
     * Questions that can be used: right exam type, right question type, right status, and a valid set
     * of answers for the question type (exactly one correct answer, or exactly one best and one worst).
     *
     * @return Collection<int, int>
     */
    private function eligibleIds(ExamType $type, int $questionTypeId, string $mode): Collection
    {
        $query = Question::query()
            ->where('exam_type_id', $type->id)
            ->where('question_type_id', $questionTypeId)
            ->when(config('portal.question_status'), fn ($q, $status) => $q->where('status', $status))
            ->has('answerOptions', '>=', 2)
            ->has('answerOptions', '=', 1, 'and', fn ($q) => $q->where('is_best', true));

        if ($mode === QuestionType::MODE_BEST_WORST) {
            $query->has('answerOptions', '=', 1, 'and', fn ($q) => $q->where('is_worst', true));
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id);
    }

    /**
     * Everything the exam needs, frozen at start (so later edits to the question bank never change an
     * attempt). Contains the correct answers: never send this array to the browser.
     *
     * @return array<string, mixed>
     */
    public static function snapshotFor(Question $question): array
    {
        $type = $question->questionType;

        return [
            'type_id' => $type?->id,
            'type' => $type->code ?? 'situational',
            'type_name_en' => $type->name_en ?? 'Situational Judgment',
            'type_name_ar' => $type->name_ar ?? 'الحكم الموقفي',
            'mode' => $type->mode ?? QuestionType::MODE_BEST_WORST,
            'best_points' => $type->best_points ?? 1,
            'worst_points' => $type->worst_points ?? 1,
            'statement_en' => $question->statement_en,
            'statement_ar' => $question->statement_ar,
            'rationale_en' => $question->rationale_en,
            'rationale_ar' => $question->rationale_ar,
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
