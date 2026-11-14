<?php

declare(strict_types=1);

namespace App\Services\Exams;

use App\Models\ExamAttempt;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Part 1 -> break -> part 2 -> done. The server decides the time, not the browser.
 * There is no scheduler: every request calls sync(), which moves the attempt forward if a deadline
 * has passed (even if the user was away for hours).
 */
class ExamClock
{
    public function __construct(private readonly ExamScorer $scorer) {}

    /** part1 | break | part2 | done */
    public function phase(ExamAttempt $attempt): string
    {
        return match (true) {
            $attempt->completed_at !== null => 'done',
            $attempt->part_2_started_at !== null => 'part2',
            $attempt->break_started_at !== null => 'break',
            default => 'part1',
        };
    }

    /** When the part (or the break) the user is in right now ends. */
    public function deadline(ExamAttempt $attempt): ?CarbonInterface
    {
        return match ($this->phase($attempt)) {
            'part1' => $attempt->part_1_ends_at,
            'break' => $attempt->break_started_at->copy()->addMinutes((int) config('portal.exam.break_minutes')),
            'part2' => $attempt->expires_at,
            default => null,
        };
    }

    /** Applies every deadline that has passed and returns the fresh attempt. */
    public function sync(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->completed_at) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt): ExamAttempt {
            $a = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $partMinutes = (int) config('portal.exam.minutes_per_part');
            $breakMinutes = (int) config('portal.exam.break_minutes');

            // Part 1 time is up: the break begins exactly when part 1 ended.
            if ($this->phase($a) === 'part1' && $a->part_1_ends_at && $now->gte($a->part_1_ends_at)) {
                $a->break_started_at = $a->part_1_ends_at;
            }

            // Break time is up: part 2 starts by itself.
            if ($this->phase($a) === 'break') {
                $auto = $a->break_started_at->copy()->addMinutes($breakMinutes);

                if ($now->gte($auto)) {
                    $a->part_2_started_at = $auto;
                    $a->expires_at = $auto->copy()->addMinutes($partMinutes);
                }
            }

            // Part 2 time is up: the exam closes and only answered questions count.
            if ($this->phase($a) === 'part2' && $a->expires_at && $now->gte($a->expires_at)) {
                $this->finish($a, $a->expires_at);
            }

            $a->save();

            return $a;
        });
    }

    /** The user submitted part 1: the break starts now. */
    public function startBreak(ExamAttempt $attempt): void
    {
        if ($this->phase($attempt) === 'part1') {
            $attempt->update(['break_started_at' => now()]);
        }
    }

    /** The user chose not to wait for the end of the break. */
    public function startPart2(ExamAttempt $attempt): void
    {
        if ($this->phase($attempt) === 'break') {
            $now = now();
            $attempt->update([
                'part_2_started_at' => $now,
                'expires_at' => $now->copy()->addMinutes((int) config('portal.exam.minutes_per_part')),
            ]);
        }
    }

    /** The user submitted the final part. */
    public function complete(ExamAttempt $attempt): void
    {
        if ($this->phase($attempt) === 'part2') {
            $this->finish($attempt, now());
            $attempt->save();
        }
    }

    private function finish(ExamAttempt $attempt, CarbonInterface $at): void
    {
        $attempt->completed_at = $at;
        $attempt->status = ExamAttempt::STATUS_COMPLETED;
        $attempt->score = $this->scorer->score($attempt);
    }
}
