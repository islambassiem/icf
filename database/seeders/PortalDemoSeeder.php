<?php

namespace Database\Seeders;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamResponse;
use App\Models\ExamType;
use App\Models\Payment;
use App\Models\QuestionType;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Exams\ExamClock;
use App\Services\Exams\ExamStarter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data so you can review the portal.
 *   php artisan db:seed --class=PortalDemoSeeder
 * Uses DEMO_USER_EMAIL from .env, or the first user. Plan prices and questions are placeholders.
 */
class PortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $now = now();
        $stamp = fn (): array => ['created_at' => $now, 'updated_at' => $now];

        $sar = CurrencyEnum::SAR;
        $paid = PaymentStatusEnum::PAID;
        $failed = PaymentStatusEnum::FAILD;

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
        $old->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0002', 'amount' => 99, 'currency' => $sar, 'status' => $failed, 'failure_reason' => 'declined', 'paid_at' => null]);
        $current->payments()->create(['provider' => 'demo', 'provider_payment_id' => 'DEMO-0003', 'amount' => 249, 'currency' => $sar, 'status' => $paid, 'paid_at' => now()->subDays(40)]);

        // ---- categories ----
        $upsert = fn (string $table, array $rows) => collect($rows)->map(function ($r) use ($table, $stamp) {
            $id = DB::table($table)->where('name_en', $r[0])->value('id');

            return $id ?? DB::table($table)->insertGetId(['name_en' => $r[0], 'name_ar' => $r[1], 'description' => $r[2] ?? null] + $stamp());
        })->values();

        $types = $upsert('exam_types', [['ACC', 'ACC', 'Associate Certified Coach'], ['PCC', 'PCC', 'Professional Certified Coach']]);
        $profiles = $upsert('profile_types', [['Competency', 'الكفاية'], ['Skill', 'المهارة'], ['Value', 'القيمة']]);

        // The exam structure (parts, minutes, 18 knowledge + 22 situational per part, points).
        $this->call(ExamStructureSeeder::class);
        $knowledge = QuestionType::query()->where('code', 'knowledge')->firstOrFail();
        $situational = QuestionType::query()->where('code', 'situational')->firstOrFail();

        // ---- question bank: 40 knowledge + 50 situational per exam type (an exam needs 36 + 44) ----
        foreach ($types as $typeId) {
            if (DB::table('questions')->where('exam_type_id', $typeId)->where('statement_en', 'like', 'Demo %')->exists()) {
                continue;
            }

            foreach ([[$knowledge, 40, 'knowledge'], [$situational, 50, 'situational']] as [$qt, $count, $label]) {
                for ($i = 1; $i <= $count; $i++) {
                    $qid = DB::table('questions')->insertGetId([
                        'statement_en' => "Demo {$label} question {$i}", 'statement_ar' => "سؤال تجريبي {$i}",
                        'exam_type_id' => $typeId, 'profile_type_id' => $profiles[$i % 3],
                        'question_type_id' => $qt->id, 'status' => config('portal.question_status') ?: 'active',
                        'rationale_en' => 'Demo reasoning: the best choice keeps the client in charge of the decision.',
                        'rationale_ar' => 'تعليل تجريبي: أفضل خيار يُبقي القرار بيد العميل.',
                    ] + $stamp());

                    // Option 1 is the best / correct answer; option 2 is the worst (situational only).
                    foreach ([0, 1, 2, 3] as $o) {
                        DB::table('answer_options')->insert([
                            'question_id' => $qid, 'statement_en' => 'Option '.($o + 1), 'statement_ar' => 'خيار '.($o + 1),
                            'comment_en' => $o === 0 ? 'Demo comment.' : '', 'comment_ar' => $o === 0 ? 'تعليق تجريبي.' : '',
                            'is_best' => $o === 0, 'is_worst' => $qt->mode === QuestionType::MODE_BEST_WORST && $o === 1,
                        ] + $stamp());
                    }
                }
            }
        }

        // ---- attempts, taken through the real starter and clock ----
        $starter = resolve(ExamStarter::class);
        $clock = resolve(ExamClock::class);

        $answer = function (ExamAttempt $attempt, float $accuracy, ?int $limit = null): void {
            $count = 0;

            foreach ($attempt->attemptQuestions()->where('part_number', 1)->orderBy('position')->get() as $question) {
                if ($limit !== null && $count >= $limit) {
                    break;
                }

                $this->respond($question, $accuracy);
                $count++;
            }
        };

        // [exam type index, days ago, accuracy]
        foreach ([[0, 31, .55], [1, 22, .65], [0, 12, .6], [0, 4, .75]] as [$ti, $daysAgo, $accuracy]) {
            /** @var ExamType $type */
            $type = ExamType::query()->findOrFail($types[$ti]);
            $attempt = $starter->start($user, $type);

            foreach ([1, 2] as $part) {
                foreach ($attempt->refresh()->attemptQuestions()->where('part_number', $part)->orderBy('position')->get() as $question) {
                    $this->respond($question, $accuracy);
                }

                $part === 1 ? $clock->startBreak($attempt->refresh()) : $clock->complete($attempt->refresh());

                if ($part === 1) {
                    $clock->startPart2($attempt->refresh());
                }
            }

            // Move the finished exam back in time.
            $start = now()->subDays($daysAgo);
            $attempt->refresh()->update([
                'started_at' => $start, 'part_1_ends_at' => $start->copy()->addMinutes(70),
                'break_started_at' => $start->copy()->addMinutes(70), 'part_2_started_at' => $start->copy()->addMinutes(80),
                'expires_at' => $start->copy()->addMinutes(150), 'completed_at' => $start->copy()->addMinutes(148),
            ]);
        }

        // One exam still running: part 1, twelve questions answered.
        /** @var ExamType $type */
        $type = ExamType::query()->findOrFail($types[$ti]);
        $running = $starter->start($user, $type);
        $answer($running, .6, 12);
        $running->update(['started_at' => now()->subMinutes(35), 'part_1_ends_at' => now()->addMinutes(35), 'expires_at' => now()->addMinutes(115)]);
    }

    /** Answers one exam question: right with the given probability, wrong otherwise. */
    private function respond(ExamAttemptQuestion $question, float $accuracy): void
    {
        $options = $question->snapshotOptions();
        $best = (int) $options->firstWhere('is_best', true)['id'];
        $worst = $question->isSingle() ? null : (int) $options->firstWhere('is_worst', true)['id'];
        $others = $options->filter(fn ($o): bool => ! $o['is_best'] && ! $o['is_worst'])->pluck('id')->map(fn ($i): int => (int) $i)->values();
        $right = fn (): bool => mt_rand() / mt_getrandmax() < $accuracy;

        ExamResponse::query()->updateOrCreate(['exam_attempt_question_id' => $question->id], [
            'best_answer_option_id' => $right() ? $best : $others[0],
            'worst_answer_option_id' => $worst === null ? null : ($right() ? $worst : $others[1]),
            'answered_at' => now(),
        ]);
    }
}
