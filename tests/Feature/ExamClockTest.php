<?php

use App\Models\ExamAttempt;
use App\Services\Exams\ExamClock;
use Illuminate\Support\Facades\Date;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->t0 = Date::parse('2026-11-16 09:00:00');
    Date::setTestNow($this->t0);
    [$this->user, $this->attempt] = Portal::startedExam();
    $this->clock = resolve(ExamClock::class);
});

afterEach(fn () => Date::setTestNow());

/** Moves the clock to N minutes after the exam started, then lets the exam catch up. */
function minutesIn(object $test, int $minutes): ExamAttempt
{
    $test->travelTo($test->t0->copy()->addMinutes($minutes));

    return $test->clock->sync($test->attempt->fresh());
}

describe('part 1', function (): void {
    it('starts in part 1 with the part 1 deadline', function (): void {
        expect($this->clock->phase($this->attempt))->toBe('part1')
            ->and($this->clock->deadline($this->attempt)->equalTo($this->t0->copy()->addMinutes(70)))->toBeTrue();
    });

    it('is still part 1 just before time is up', function (): void {
        $attempt = minutesIn($this, 69);

        expect($this->clock->phase($attempt))->toBe('part1')->and($attempt->break_started_at)->toBeNull();
    });

    it('moves to the break when part 1 time is up, counting the break from the end of part 1', function (): void {
        $attempt = minutesIn($this, 72);

        expect($this->clock->phase($attempt))->toBe('break')
            ->and($attempt->break_started_at->equalTo($this->t0->copy()->addMinutes(70)))->toBeTrue()
            ->and($this->clock->deadline($attempt)->equalTo($this->t0->copy()->addMinutes(80)))->toBeTrue();
    });
});

describe('break and part 2', function (): void {
    it('starts part 2 by itself when the break is over', function (): void {
        $attempt = minutesIn($this, 81);

        expect($this->clock->phase($attempt))->toBe('part2')
            ->and($attempt->part_2_started_at->equalTo($this->t0->copy()->addMinutes(80)))->toBeTrue()
            ->and($attempt->expires_at->equalTo($this->t0->copy()->addMinutes(150)))->toBeTrue()
            ->and($this->clock->deadline($attempt)->equalTo($this->t0->copy()->addMinutes(150)))->toBeTrue();
    });

    it('lets the user submit part 1 early, which starts the break right away', function (): void {
        $this->travelTo($this->t0->copy()->addMinutes(30));
        $this->clock->startBreak($this->attempt);

        $attempt = $this->attempt->fresh();

        expect($this->clock->phase($attempt))->toBe('break')
            ->and($attempt->break_started_at->equalTo($this->t0->copy()->addMinutes(30)))->toBeTrue();
    });

    it('does not restart the break if part 1 is submitted twice', function (): void {
        $this->travelTo($this->t0->copy()->addMinutes(30));
        $this->clock->startBreak($this->attempt);
        $this->travelTo($this->t0->copy()->addMinutes(35));
        $this->clock->startBreak($this->attempt->fresh());

        expect($this->attempt->fresh()->break_started_at->equalTo($this->t0->copy()->addMinutes(30)))->toBeTrue();
    });

    it('lets the user skip the rest of the break, giving part 2 its full time from that moment', function (): void {
        $this->travelTo($this->t0->copy()->addMinutes(30));
        $this->clock->startBreak($this->attempt);
        $this->travelTo($this->t0->copy()->addMinutes(33));
        $this->clock->startPart2($this->attempt->fresh());

        $attempt = $this->attempt->fresh();

        expect($this->clock->phase($attempt))->toBe('part2')
            ->and($attempt->part_2_started_at->equalTo($this->t0->copy()->addMinutes(33)))->toBeTrue()
            ->and($attempt->expires_at->equalTo($this->t0->copy()->addMinutes(33 + 70)))->toBeTrue();
    });

    it('cannot start part 2 before part 1 is submitted', function (): void {
        $this->clock->startPart2($this->attempt);

        expect($this->attempt->fresh()->part_2_started_at)->toBeNull();
    });
});

describe('finishing', function (): void {
    it('closes the exam and scores it when part 2 time is up', function (): void {
        $attempt = minutesIn($this, 151);

        expect($this->clock->phase($attempt))->toBe('done')
            ->and($attempt->completed_at->equalTo($this->t0->copy()->addMinutes(150)))->toBeTrue()
            ->and($attempt->status)->toBe(ExamAttempt::STATUS_COMPLETED)
            ->and($attempt->score)->not->toBeNull()
            ->and($this->clock->deadline($attempt))->toBeNull();
    });

    it('counts only the questions that were answered when time runs out', function (): void {
        Portal::answerPart($this->attempt, 1);      // part 1 fully right, part 2 never opened

        $attempt = minutesIn($this, 151);

        expect($attempt->score)->toBe(50);          // 62 of 124 points
    });

    it('catches up in one step when the user comes back hours later', function (): void {
        $attempt = minutesIn($this, 600);

        expect($attempt->completed_at->equalTo($this->t0->copy()->addMinutes(150)))->toBeTrue()
            ->and($attempt->part_2_started_at)->not->toBeNull();
    });

    it('lets the user submit the final part, closing the exam at that moment', function (): void {
        $this->clock->startBreak($this->attempt);
        $this->clock->startPart2($this->attempt->fresh());
        Portal::answerPart($this->attempt->fresh(), 1);
        Portal::answerPart($this->attempt->fresh(), 2);
        $this->travelTo($this->t0->copy()->addMinutes(100));

        $this->clock->complete($this->attempt->fresh());

        $attempt = $this->attempt->fresh();

        expect($attempt->completed_at->equalTo($this->t0->copy()->addMinutes(100)))->toBeTrue()->and($attempt->score)->toBe(100);
    });

    it('cannot finish the exam from part 1', function (): void {
        $this->clock->complete($this->attempt);

        expect($this->attempt->fresh()->completed_at)->toBeNull();
    });

    it('leaves a finished exam alone', function (): void {
        $finished = minutesIn($this, 151);
        $score = $finished->score;

        $this->travelTo($this->t0->copy()->addDays(3));
        $again = $this->clock->sync($finished->fresh());

        expect($again->completed_at->equalTo($finished->completed_at))->toBeTrue()->and($again->score)->toBe($score);
    });
});

it('uses the timing of the exam type, not fixed numbers', function (): void {
    $type = Portal::examType('PCC', ['minutes' => 30, 'break' => 5]);
    Portal::bank($type);
    $attempt = Portal::start(Portal::subscriber(), $type);

    $this->travelTo($this->t0->copy()->addMinutes(36));
    $attempt = $this->clock->sync($attempt->fresh());

    expect($attempt->part_2_started_at->equalTo($this->t0->copy()->addMinutes(35)))->toBeTrue()
        ->and($attempt->expires_at->equalTo($this->t0->copy()->addMinutes(65)))->toBeTrue();
});
