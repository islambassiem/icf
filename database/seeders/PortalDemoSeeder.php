<?php

namespace Database\Seeders;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Exams\ExamStarter;
use BackedEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

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

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Your enums: pick the case matching a word ("SAR", "paid", ...), or the first case if none matches.
        $pick = function (string $enum, string $want) {
            /** @var list<UnitEnum> $cases */
            $cases = $enum::cases();

            return collect($cases)->first(
                fn (UnitEnum $c): bool => strtolower((string) ($c instanceof BackedEnum ? $c->value : $c->name)) === strtolower($want) || strtolower($c->name) === strtolower($want)
            ) ?? $cases[0];
        };
        $sar = $pick(CurrencyEnum::class, 'SAR');
        $paid = $pick(PaymentStatusEnum::class, 'paid');
        $failed = $pick(PaymentStatusEnum::class, 'failed');

        $now = now();
        $stamp = fn (): array => ['created_at' => $now, 'updated_at' => $now];

        // ---- plans ----
        $plans = collect([['Monthly', 'شهرية', 30, 99], ['Quarterly', 'ربع سنوية', 90, 249], ['Yearly', 'سنوية', 365, 799]])
            ->map(fn ($p) => SubscriptionPlan::query()->firstOrCreate(['name_en' => $p[0]], [
                'name_ar' => $p[1], 'duration_days' => $p[2], 'price' => $p[3], 'currency' => $sar, 'is_active' => true,
            ]));

        // ---- reset this user's data (children first: no cascade on your foreign keys) ----
        $attemptIds = $user->examAttempts()->pluck('id');
        $aqIds = DB::table('exam_attempt_questions')->whereIn('attempt_id', $attemptIds)->pluck('id');
        DB::table('exam_responses')->whereIn('exam_attempt_question_id', $aqIds)->delete();
        DB::table('exam_attempt_questions')->whereIn('attempt_id', $attemptIds)->delete();
        $user->examAttempts()->delete();
        Payment::query()->whereIn('subscription_id', $user->subscriptions()->pluck('id'))->delete();
        $user->subscriptions()->delete();

        // ---- subscriptions and payments ----
        $old = $user->subscriptions()->create([
            'subscription_plan_id' => $plans[0]->id, 'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => now()->subMonths(4), 'ends_at' => now()->subMonths(3), 'amount_paid' => 99, 'currency' => $sar,
        ]);
        $current = $user->subscriptions()->create([
            'subscription_plan_id' => $plans[1]->id, 'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(40), 'ends_at' => now()->addDays(50), 'amount_paid' => 249, 'currency' => $sar,
        ]);
        $old->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0001', 'amount' => 99, 'currency' => $sar, 'status' => $paid, 'paid_at' => now()->subMonths(4)]);
        // paid_at is NOT NULL in your migration, so a failed attempt stores the attempt time.
        $old->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0002', 'amount' => 99, 'currency' => $sar, 'status' => $failed, 'failure_reason' => 'Card declined', 'paid_at' => now()->subDays(45)]);
        $current->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0003', 'amount' => 249, 'currency' => $sar, 'status' => $paid, 'paid_at' => now()->subDays(40)]);

        // ---- categories ----
        $upsert = fn (string $table, array $rows) => collect($rows)->map(function ($r) use ($table, $stamp) {
            $id = DB::table($table)->where('name_en', $r[0])->value('id');

            return $id ?? DB::table($table)->insertGetId(['name_en' => $r[0], 'name_ar' => $r[1], 'description' => $r[2] ?? null] + $stamp());
        })->values();

        $types = $upsert('exam_types', [['ACC', 'ACC', 'Associate Certified Coach'], ['PCC', 'PCC', 'Professional Certified Coach']]);
        $areas = $upsert('question_areas', [['Situational', 'موقفي'], ['Behavioural', 'سلوكي']]);
        $profiles = $upsert('profile_types', [['Competency', 'الكفاية'], ['Skill', 'المهارة'], ['Value', 'القيمة']]);

        // ---- 100 sample questions per exam type (an exam needs 80), 4 options each (option 0 best, option 3 worst) ----
        $bank = [];
        foreach ($types as $ti => $typeId) {
            $existing = DB::table('questions')->where('exam_type_id', $typeId)->where('statement_en', 'like', 'Demo question%')->pluck('id');
            if ($existing->count() >= 100) {
                $bank[$typeId] = $existing->take(100)->all();

                continue;
            }

            for ($i = 1; $i <= 100; $i++) {
                $qid = DB::table('questions')->insertGetId([
                    'statement_en' => "Demo question {$i} (type {$typeId})", 'statement_ar' => "سؤال تجريبي {$i}",
                    'exam_type_id' => $typeId, 'question_area_id' => $areas[$i % 2], 'profile_type_id' => $profiles[$i % 3],
                    'status' => config('portal.question_status') ?: 'active',
                ] + $stamp());
                foreach ([0, 1, 2, 3] as $o) {
                    DB::table('answer_options')->insert([
                        'question_id' => $qid, 'statement_en' => 'Option '.($o + 1), 'statement_ar' => 'خيار '.($o + 1),
                        'comment_en' => 'Demo comment', 'comment_ar' => 'تعليق تجريبي', 'is_best' => $o === 0, 'is_worst' => $o === 3,
                    ] + $stamp());
                }

                $bank[$typeId][] = $qid;
            }
        }

        // ---- attempts ----
        $makeAttempt = function (int $typeId, array $attrs, float $accuracy, ?int $answerOnly = null) use ($user, $current, $bank, $stamp): array {
            $attempt = ExamAttempt::query()->create([
                'user_id' => $user->id, 'subscription_id' => $current->id, 'exam_type_id' => $typeId,
            ] + $attrs);

            $questions = array_slice($bank[$typeId], 0, 20);
            $points = 0;
            $answered = 0;

            foreach ($questions as $pos => $qid) {
                $aqId = DB::table('exam_attempt_questions')->insertGetId([
                    'attempt_id' => $attempt->id, 'question_id' => $qid, 'part_number' => $pos < 10 ? 1 : 2,
                    'position' => (((int) $pos) % 10) + 1, 'question_snapshot' => json_encode(ExamStarter::snapshotFor(Question::with('answerOptions')->findOrFail((int) $qid))),
                ] + $stamp());

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
                ] + $stamp());
            }

            return [$attempt, (int) round($points / (2 * count($questions)) * 100)];
        };

        foreach ([[0, 31, .55], [1, 22, .65], [0, 12, .6], [0, 4, .75]] as [$ti, $daysAgo, $accuracy]) {
            $start = now()->subDays($daysAgo);
            [$attempt, $score] = $makeAttempt($types[$ti], [
                'status' => ExamAttempt::STATUS_COMPLETED, 'started_at' => $start,
                'part_1_ends_at' => $start->copy()->addMinutes(70), 'break_started_at' => $start->copy()->addMinutes(70),
                'part_2_started_at' => $start->copy()->addMinutes(80), 'expires_at' => $start->copy()->addMinutes(150),
                'completed_at' => $start->copy()->addMinutes(148),
            ], $accuracy);
            $attempt->update(['score' => $score]);
        }

        $start = now()->subMinutes(35);
        $makeAttempt($types[1], [
            'status' => ExamAttempt::STATUS_IN_PROGRESS, 'started_at' => $start,
            'part_1_ends_at' => $start->copy()->addMinutes(70), 'expires_at' => $start->copy()->addMinutes(150),
        ], .6, answerOnly: 12);
    }
}
