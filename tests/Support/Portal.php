<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamPart;
use App\Models\ExamPartSection;
use App\Models\ExamResponse;
use App\Models\ExamType;
use App\Models\Payment;
use App\Models\ProfileType;
use App\Models\QuestionType;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Exams\ExamClock;
use App\Services\Exams\ExamStarter;
use App\Support\EnumPick;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Small builders so every test reads like a story. They use the models and query builder directly,
 * so the tests do not depend on how your factories are defined (except UserFactory).
 */
final class Portal
{
    public static function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    public static function currency(string $code = 'SAR'): mixed
    {
        return EnumPick::case(CurrencyEnum::class, $code);
    }

    public static function paymentStatus(string $status): mixed
    {
        return EnumPick::case(PaymentStatusEnum::class, $status);
    }

    public static function plan(array $attributes = []): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create($attributes + [
            'name_en' => 'Monthly',
            'name_ar' => 'شهرية',
            'duration_days' => 30,
            'price' => 99,
            'currency' => self::currency(),
            'is_active' => true,
        ]);
    }

    /**
     * A subscription row. Defaults to one that is active right now.
     *
     * @param  array<string, mixed>  $attributes  may contain 'plan' => SubscriptionPlan
     */
    public static function subscribe(User $user, array $attributes = []): Subscription
    {
        $plan = $attributes['plan'] ?? self::plan();
        unset($attributes['plan']);

        return Subscription::query()->create($attributes + [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'amount_paid' => $plan->price,
            'currency' => $plan->currency,
        ]);
    }

    /** A user who can take exams right now. */
    public static function subscriber(): User
    {
        $user = self::user();
        self::subscribe($user);

        return $user;
    }

    public static function payment(Subscription $subscription, string $status = 'paid', array $attributes = []): Payment
    {
        $plan = $subscription->subscriptionPlan;

        return Payment::query()->create($attributes + [
            'subscription_id' => $subscription->id,
            'provider' => 'fake',
            'provider_payment_id' => $status === 'paid' ? 'fake_pay_'.uniqid() : null,
            'provider_checkout_id' => 'fake_chk_'.uniqid(),
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => self::paymentStatus($status),
            'paid_at' => $status === 'paid' ? now() : null,
            'failure_reason' => $status === 'failed' ? 'declined' : null,
        ]);
    }

    public static function questionType(string $code): QuestionType
    {
        return $code === 'knowledge'
            ? QuestionType::query()->firstOrCreate(['code' => 'knowledge'], ['name_en' => 'Knowledge', 'name_ar' => 'معرفي', 'mode' => QuestionType::MODE_SINGLE, 'best_points' => 1, 'worst_points' => 0])
            : QuestionType::query()->firstOrCreate(['code' => 'situational'], ['name_en' => 'Situational Judgment', 'name_ar' => 'الحكم الموقفي', 'mode' => QuestionType::MODE_BEST_WORST, 'best_points' => 1, 'worst_points' => 1]);
    }

    /**
     * An exam type WITH its structure: by default 2 parts, each 18 knowledge + 22 situational questions,
     * 70 minutes per part and a 10 minute break (the real ICF layout). Pass overrides to change it.
     *
     * @param  array{minutes?: int, break?: int, knowledge?: int, situational?: int}  $layout
     */
    public static function examType(string $name = 'ACC', array $layout = []): ExamType
    {
        $type = ExamType::query()->firstOrCreate(['name_en' => $name], ['name_ar' => $name, 'description' => $name.' exam']);

        $knowledge = self::questionType('knowledge');
        $situational = self::questionType('situational');

        foreach ([1, 2] as $number) {
            $part = ExamPart::query()->firstOrCreate(['exam_type_id' => $type->id, 'part_number' => $number], ['minutes' => $layout['minutes'] ?? 70, 'break_minutes' => $number === 1 ? ($layout['break'] ?? 10) : 0]);

            ExamPartSection::query()->firstOrCreate(['exam_part_id' => $part->id, 'position' => 1], ['question_type_id' => $knowledge->id, 'question_count' => $layout['knowledge'] ?? 18]);
            ExamPartSection::query()->firstOrCreate(['exam_part_id' => $part->id, 'position' => 2], ['question_type_id' => $situational->id, 'question_count' => $layout['situational'] ?? 22]);
        }

        return $type;
    }

    /**
     * Question-bank rows (bulk inserted for speed).
     *  - knowledge questions: 4 options, option 1 is the single correct answer
     *  - situational questions: 4 options, #1 best, #2 worst, #3 and #4 neutral
     *
     * @param  array<string, mixed>  $question  overrides for the questions table
     * @return Collection<int, int> question ids
     */
    public static function bank(ExamType $type, int $knowledge = 40, int $situational = 50, array $question = []): Collection
    {
        $profiles = collect([
            ProfileType::query()->firstOrCreate(['name_en' => 'Competency'], ['name_ar' => 'الكفاية']),
            ProfileType::query()->firstOrCreate(['name_en' => 'Skill'], ['name_ar' => 'المهارة']),
            ProfileType::query()->firstOrCreate(['name_en' => 'Value'], ['name_ar' => 'القيمة']),
        ]);

        $now = now();
        $ids = collect();

        foreach ([['knowledge', $knowledge], ['situational', $situational]] as [$code, $count]) {
            $questionType = self::questionType($code);

            for ($i = 1; $i <= $count; $i++) {
                $id = DB::table('questions')->insertGetId($question + [
                    'statement_en' => ucfirst($code)." question {$i} ({$type->name_en})",
                    'statement_ar' => "السؤال {$i}",
                    'exam_type_id' => $type->id,
                    'profile_type_id' => $profiles[$i % 3]->id,
                    'question_type_id' => $questionType->id,
                    'rationale_en' => "Rationale for {$code} question {$i}",
                    'rationale_ar' => 'التعليل',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('answer_options')->insert(collect([
                    ['is_best' => true, 'is_worst' => false],
                    ['is_best' => false, 'is_worst' => $questionType->mode === QuestionType::MODE_BEST_WORST],
                    ['is_best' => false, 'is_worst' => false],
                    ['is_best' => false, 'is_worst' => false],
                ])->map(fn (array $flags, int $n): array => $flags + [
                    'question_id' => $id,
                    'statement_en' => 'Option '.($n + 1),
                    'statement_ar' => 'خيار '.($n + 1),
                    'comment_en' => 'Reason '.($n + 1)." for {$code} question {$i}",
                    'comment_ar' => 'السبب '.($n + 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());

                $ids->push($id);
            }
        }

        return $ids;
    }

    public static function start(User $user, ?ExamType $type = null): ExamAttempt
    {
        return resolve(ExamStarter::class)->start($user, $type ?? self::examType());
    }

    /** A subscribed user with a full question bank and a freshly started exam. */
    public static function startedExam(): array
    {
        $type = self::examType();
        self::bank($type);
        $user = self::subscriber();

        return [$user, self::start($user, $type), $type];
    }

    /** @return array{best: int, worst: ?int, others: list<int>} option ids of an exam question (worst is null for knowledge) */
    public static function options(ExamAttemptQuestion $question): array
    {
        $options = $question->snapshotOptions();

        return [
            'best' => (int) $options->firstWhere('is_best', true)['id'],
            'worst' => $question->isSingle() ? null : (int) $options->firstWhere('is_worst', true)['id'],
            'others' => $options->filter(fn (array $o): bool => ! $o['is_best'] && ! $o['is_worst'])->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    /**
     * Answers one exam question. $bestRight is the correct answer for knowledge questions; $worstRight only
     * matters for situational questions.
     */
    public static function answer(ExamAttemptQuestion $question, bool $bestRight = true, bool $worstRight = true): ExamResponse
    {
        $o = self::options($question);

        return ExamResponse::query()->updateOrCreate(['exam_attempt_question_id' => $question->id], [
            'best_answer_option_id' => $bestRight ? $o['best'] : $o['others'][0],
            'worst_answer_option_id' => $o['worst'] === null ? null : ($worstRight ? $o['worst'] : $o['others'][1]),
            'answered_at' => now(),
        ]);
    }

    public static function answerPart(ExamAttempt $attempt, int $part, bool $bestRight = true, bool $worstRight = true, ?int $only = null): void
    {
        $questions = $attempt->attemptQuestions()->where('part_number', $part)->orderBy('position')->get();

        foreach ($only === null ? $questions : $questions->take($only) as $question) {
            self::answer($question, $bestRight, $worstRight);
        }
    }

    /** Takes the whole exam through the clock: answers part 1, break, answers part 2, finishes. */
    public static function completeExam(ExamAttempt $attempt, bool $bestRight = true, bool $worstRight = true): ExamAttempt
    {
        $clock = resolve(ExamClock::class);

        self::answerPart($attempt, 1, $bestRight, $worstRight);
        $clock->startBreak($attempt);
        $clock->startPart2($attempt->refresh());
        self::answerPart($attempt->refresh(), 2, $bestRight, $worstRight);
        $clock->complete($attempt->refresh());

        return $attempt->refresh();
    }

    /** A specific question of an attempt, by part and position. */
    public static function question(ExamAttempt $attempt, int $part, int $position): ExamAttemptQuestion
    {
        return $attempt->attemptQuestions()->where('part_number', $part)->where('position', $position)->firstOrFail();
    }

    /** An attempt row without any questions (for list and pagination tests). */
    public static function rawAttempt(User $user, ExamType $type, array $attributes = []): ExamAttempt
    {
        $subscription = $user->subscriptions()->latest('id')->first() ?? self::subscribe($user);

        return ExamAttempt::query()->create($attributes + [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'exam_type_id' => $type->id,
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'part_1_ends_at' => now()->addMinutes(70),
            'expires_at' => now()->addMinutes(150),
        ]);
    }
}
