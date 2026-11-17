<?php

use App\Models\ExamAttempt;
use App\Models\ExamPart;
use Illuminate\Support\Facades\Date;
use Tests\Support\Portal;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-11-16 10:00:00'));
    $this->user = Portal::subscriber();
    $this->type = Portal::examType();
});

afterEach(fn () => Date::setTestNow());

describe('state', function (): void {
    it('is in progress while time remains', function (): void {
        expect(Portal::rawAttempt($this->user, $this->type)->state())->toBe('in_progress');
    });

    it('is expired once the overall deadline passed without finishing', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type, ['expires_at' => now()->subMinute()]);

        expect($attempt->state())->toBe('expired');
    });

    it('is completed once finished, even after the deadline', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type, ['expires_at' => now()->subHour(), 'completed_at' => now()->subHour()]);

        expect($attempt->state())->toBe('completed');
    });

    it('does not read the free-text status column', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type, ['status' => 'Anything at all']);

        expect($attempt->state())->toBe('in_progress');
    });
});

describe('scopes', function (): void {
    it('finds completed attempts', function (): void {
        Portal::rawAttempt($this->user, $this->type, ['completed_at' => now()]);
        Portal::rawAttempt($this->user, $this->type);

        expect(ExamAttempt::completed()->count())->toBe(1);
    });

    it('finds only attempts that are still running', function (): void {
        $running = Portal::rawAttempt($this->user, $this->type);
        Portal::rawAttempt($this->user, $this->type, ['completed_at' => now()]);
        Portal::rawAttempt($this->user, $this->type, ['expires_at' => now()->subMinute()]);

        expect(ExamAttempt::inProgress()->pluck('id')->all())->toBe([$running->id]);
    });
});

describe('score', function (): void {
    it('is null until the exam is scored', function (): void {
        expect(Portal::rawAttempt($this->user, $this->type)->scorePercent())->toBeNull();
    });

    it('is shown as a percentage when the score is stored out of 100', function (): void {
        expect(Portal::rawAttempt($this->user, $this->type, ['score' => 62])->scorePercent())->toBe(62.0);
    });

    it('is converted to a percentage when the score is stored in raw points', function (): void {
        config(['portal.score_max' => 160]);

        expect(Portal::rawAttempt($this->user, $this->type, ['score' => 80])->scorePercent())->toBe(50.0);
    });
});

describe('timing', function (): void {
    it('is in part 1 with the part 1 deadline at the start', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type);

        expect($attempt->currentPart())->toBe(1)->and($attempt->onBreak())->toBeFalse()
            ->and($attempt->currentDeadline()->equalTo($attempt->part_1_ends_at))->toBeTrue();
    });

    it('is on a break for the break length defined by the exam rules', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type, ['break_started_at' => now()]);

        expect($attempt->onBreak())->toBeTrue()->and($attempt->currentPart())->toBe(1)
            ->and($attempt->currentDeadline()->equalTo(now()->addMinutes(10)))->toBeTrue();
    });

    it('is in part 2 once it started, with the overall deadline', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type, ['break_started_at' => now()->subMinutes(10), 'part_2_started_at' => now()]);

        expect($attempt->currentPart())->toBe(2)->and($attempt->onBreak())->toBeFalse()
            ->and($attempt->currentDeadline()->equalTo($attempt->expires_at))->toBeTrue();
    });
});

describe('structure', function (): void {
    it('keeps the rules the exam started with', function (): void {
        $type = Portal::examType('PCC', ['minutes' => 30, 'break' => 5]);
        Portal::bank($type);
        $attempt = Portal::start($this->user, $type);

        expect($attempt->partMinutes(1))->toBe(30)->and($attempt->partMinutes(2))->toBe(30)->and($attempt->breakMinutes())->toBe(5);
    });

    it('ignores later edits to the exam rules', function (): void {
        $type = Portal::examType('PCC');
        Portal::bank($type);
        $attempt = Portal::start($this->user, $type);

        ExamPart::query()->where('exam_type_id', $type->id)->update(['minutes' => 5, 'break_minutes' => 1]);

        expect($attempt->fresh()->partMinutes(1))->toBe(70)->and($attempt->fresh()->breakMinutes())->toBe(10);
    });

    it('falls back to the old defaults for attempts without a saved structure', function (): void {
        $attempt = Portal::rawAttempt($this->user, $this->type);

        expect($attempt->structure_snapshot)->toBeNull()
            ->and($attempt->partMinutes(1))->toBe(70)->and($attempt->partMinutes(2))->toBe(70)->and($attempt->breakMinutes())->toBe(10);
    });
});

describe('breakdown', function (): void {
    it('reports 100% in every group when everything is right', function (): void {
        [, $attempt] = Portal::startedExam();
        Portal::completeExam($attempt);

        $breakdown = $attempt->fresh()->breakdown();

        expect(collect($breakdown['type'])->pluck('percent')->unique()->all())->toBe([100])
            ->and(collect($breakdown['profile'])->pluck('percent')->unique()->all())->toBe([100]);
    });

    it('separates knowledge from situational results', function (): void {
        [, $attempt] = Portal::startedExam();
        foreach ($attempt->attemptQuestions()->get() as $question) {
            Portal::answer($question, $question->isSingle(), false);   // knowledge right, situational wrong
        }

        $types = collect($attempt->breakdown()['type'])->keyBy('name');

        expect($types['Knowledge']['percent'])->toBe(100)->and($types['Knowledge']['count'])->toBe(36)
            ->and($types['Situational Judgment']['percent'])->toBe(0)->and($types['Situational Judgment']['count'])->toBe(44);
    });

    it('counts unanswered questions as zero', function (): void {
        [, $attempt] = Portal::startedExam();

        expect(collect($attempt->breakdown()['type'])->pluck('percent')->unique()->all())->toBe([0]);
    });

    it('shows group names in Arabic', function (): void {
        [, $attempt] = Portal::startedExam();
        app()->setLocale('ar');

        expect(collect($attempt->breakdown()['type'])->pluck('name')->all())->toContain('معرفي', 'الحكم الموقفي');
    });

    it('has no question-area group any more', function (): void {
        [, $attempt] = Portal::startedExam();

        expect(array_keys($attempt->breakdown()))->toBe(['profile', 'type']);
    });
});
