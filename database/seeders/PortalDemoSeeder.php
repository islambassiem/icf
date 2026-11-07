<?php

namespace Database\Seeders;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demo data so you can review the dashboard.
 *   php artisan db:seed --class=PortalDemoSeeder
 * Uses DEMO_USER_EMAIL from .env, or the first user.
 * Plan prices and questions are placeholders. Uses the query builder for the question
 * bank tables, so it works with whatever models you have for them.
 */
class PortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first() ?? User::first();

        if (! $user) {
            $this->command?->warn('No user found. Register one first.');

            return;
        }

        $sar = $this->pickEnumCase(CurrencyEnum::class, 'SAR');
        $paid = $this->pickEnumCase(PaymentStatusEnum::class, 'paid');
        $failed = $this->pickEnumCase(PaymentStatusEnum::class, 'failed');

        $now = now();
        $stamp = fn (): array => ['created_at' => $now, 'updated_at' => $now];

        $plans = $this->createPlans($sar);

        $this->resetUserData($user);

        $old = $user->subscriptions()->create([
            'subscription_plan_id' => $plans[0]->id, 'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => now()->subMonths(4), 'ends_at' => now()->subMonths(3), 'amount_paid' => 99, 'currency' => $sar,
        ]);
        $current = $user->subscriptions()->create([
            'subscription_plan_id' => $plans[1]->id, 'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(40), 'ends_at' => now()->addDays(50), 'amount_paid' => 249, 'currency' => $sar,
        ]);
        $old->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0001', 'amount' => 99, 'currency' => $sar, 'status' => $paid, 'paid_at' => now()->subMonths(4)]);
        $old->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0002', 'amount' => 99, 'currency' => $sar, 'status' => $failed, 'failure_reason' => 'Card declined', 'paid_at' => now()->subDays(45)]);
        $current->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0003', 'amount' => 249, 'currency' => $sar, 'status' => $paid, 'paid_at' => now()->subDays(40)]);

        $types = $this->upsertRows('exam_types', [['ACC', 'ACC', 'Associate Certified Coach'], ['PCC', 'PCC', 'Professional Certified Coach']], $stamp);
        $areas = $this->upsertRows('question_areas', [['Situational', 'موقفي'], ['Behavioural', 'سلوكي']], $stamp);
        $profiles = $this->upsertRows('profile_types', [['Competency', 'الكفاية'], ['Skill', 'المهارة'], ['Value', 'القيمة']], $stamp);

        $bank = $this->seedQuestionBank(
            array_values(array_map(static fn ($id): int => (int) $id, $types->all())),
            array_values(array_map(static fn ($id): int => (int) $id, $areas->all())),
            array_values(array_map(static fn ($id): int => (int) $id, $profiles->all())),
            $stamp,
        );

        foreach ([[0, 31, .55], [1, 22, .65], [0, 12, .6], [0, 4, .75]] as [$ti, $daysAgo, $accuracy]) {
            $start = now()->subDays($daysAgo);
            [$attempt, $score] = $this->makeAttempt($user, $current, $bank, $types[$ti], [
                'status' => ExamAttempt::STATUS_COMPLETED, 'started_at' => $start,
                'part_1_ends_at' => $start->copy()->addMinutes(70), 'break_started_at' => $start->copy()->addMinutes(70),
                'part_2_started_at' => $start->copy()->addMinutes(80), 'expires_at' => $start->copy()->addMinutes(150),
                'completed_at' => $start->copy()->addMinutes(148),
            ], $accuracy);
            $attempt->update(['score' => $score]);
        }

        $start = now()->subMinutes(35);
        $this->makeAttempt($user, $current, $bank, $types[1], [
            'status' => ExamAttempt::STATUS_IN_PROGRESS, 'started_at' => $start,
            'part_1_ends_at' => $start->copy()->addMinutes(70), 'expires_at' => $start->copy()->addMinutes(150),
        ], .6, answerOnly: 12);
    }

    private function pickEnumCase(string $enumClass, string $want): mixed
    {
        $cases = $enumClass::cases();

        if ($cases === []) {
            return null;
        }

        foreach ($cases as $case) {
            if (strtolower((string) ($case->value ?? $case->name)) === strtolower($want)
                || strtolower($case->name) === strtolower($want)) {
                return $case;
            }
        }

        return $cases[0];
    }

    /** @return Collection<int, SubscriptionPlan> */
    private function createPlans(mixed $currency): Collection
    {
        return collect([['Monthly', 'شهرية', 30, 99], ['Quarterly', 'ربع سنوية', 90, 249], ['Yearly', 'سنوية', 365, 799]])
            ->map(fn ($plan) => SubscriptionPlan::firstOrCreate(['name_en' => $plan[0]], [
                'name_ar' => $plan[1], 'duration_days' => $plan[2], 'price' => $plan[3], 'currency' => $currency, 'is_active' => true,
            ]));
    }

    private function resetUserData(User $user): void
    {
        $attemptIds = $user->examAttempts()->pluck('id');
        $aqIds = DB::table('exam_attempt_questions')->whereIn('attempt_id', $attemptIds)->pluck('id');
        DB::table('exam_responses')->whereIn('exam_attempt_question_id', $aqIds)->delete();
        DB::table('exam_attempt_questions')->whereIn('attempt_id', $attemptIds)->delete();
        $user->examAttempts()->delete();
        Payment::whereIn('subscription_id', $user->subscriptions()->pluck('id'))->delete();
        $user->subscriptions()->delete();
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string}>  $rows
     * @return Collection<int, mixed>
     */
    private function upsertRows(string $table, array $rows, callable $stamp): Collection
    {
        return collect($rows)->map(function (array $row) use ($table, $stamp) {
            $id = DB::table($table)->where('name_en', $row[0])->value('id');

            return $id ?? DB::table($table)->insertGetId(['name_en' => $row[0], 'name_ar' => $row[1], 'description' => $row[2] ?? null] + $stamp());
        })->values();
    }

    /**
     * @param  list<int>  $types
     * @param  list<int>  $areas
     * @param  list<int>  $profiles
     * @return array<int, array<int, int>>
     */
    private function seedQuestionBank(array $types, array $areas, array $profiles, callable $stamp): array
    {
        $bank = [];

        foreach ($types as $typeId) {
            $existing = DB::table('questions')->where('exam_type_id', $typeId)->where('statement_en', 'like', 'Demo question%')->pluck('id');
            if ($existing->count() >= 20) {
                $bank[$typeId] = $existing->take(20)->map(fn ($id): int => (int) $id)->values()->all();

                continue;
            }

            for ($i = 1; $i <= 20; $i++) {
                $qid = DB::table('questions')->insertGetId([
                    'statement_en' => "Demo question {$i} (type {$typeId})", 'statement_ar' => "سؤال تجريبي {$i}",
                    'exam_type_id' => $typeId, 'question_area_id' => $areas[$i % 2], 'profile_type_id' => $profiles[$i % 3],
                    'status' => 'active',
                ] + $stamp());

                foreach ([0, 1, 2, 3] as $o) {
                    DB::table('answer_options')->insert([
                        'question_id' => $qid, 'statement_en' => 'Option '.($o + 1), 'statement_ar' => 'خيار '.($o + 1),
                        'comment_en' => 'Demo comment', 'comment_ar' => 'تعليق تجريبي', 'is_best' => $o === 0, 'is_worst' => $o === 3,
                    ] + $stamp());
                }

                $bank[$typeId][] = (int) $qid;
            }
        }

        return $bank;
    }

    /**
     * @param  array<int, array<int, int>>  $bank
     * @param  array<string, mixed>  $attrs
     * @return array{0: ExamAttempt, 1: int}
     */
    private function makeAttempt(User $user, Subscription $current, array $bank, int $typeId, array $attrs, float $accuracy, ?int $answerOnly = null): array
    {
        $attempt = ExamAttempt::create([
            'user_id' => $user->id, 'subscription_id' => $current->id, 'exam_type_id' => $typeId,
        ] + $attrs);

        $questions = array_slice($bank[$typeId], 0, 20);
        $points = 0;
        $answered = 0;

        foreach ($questions as $pos => $qid) {
            $aqId = DB::table('exam_attempt_questions')->insertGetId([
                'attempt_id' => $attempt->id, 'question_id' => $qid, 'part_number' => $pos < 10 ? 1 : 2,
                'position' => ($pos % 10) + 1, 'question_snapshot' => json_encode(['id' => $qid]),
            ] + ['created_at' => now(), 'updated_at' => now()]);

            if ($answerOnly !== null && $answered >= $answerOnly) {
                continue;
            }

            $options = DB::table('answer_options')->where('question_id', $qid)->orderBy('id')->pluck('id')->all();
            $bestOk = mt_rand() / mt_getrandmax() < $accuracy;
            $worstOk = mt_rand() / mt_getrandmax() < $accuracy;
            $points += (int) $bestOk + (int) $worstOk;
            $answered++;

            DB::table('exam_responses')->insert([
                'exam_attempt_question_id' => $aqId,
                'best_answer_option_id' => $bestOk ? $options[0] : $options[1],
                'worst_answer_option_id' => $worstOk ? $options[3] : $options[2],
                'answered_at' => $attempt->started_at,
            ] + ['created_at' => now(), 'updated_at' => now()]);
        }

        return [$attempt, (int) round($points / (2 * count($questions)) * 100)];
    }
}
