<?php

use App\Models\ExamResponse;
use Tests\Support\Portal;

// Part 1 layout: positions 1-18 are knowledge questions, 19-40 are situational judgment.
beforeEach(function (): void {
    [$this->user, $this->attempt] = Portal::startedExam();
    $this->knowledge = Portal::question($this->attempt, 1, 1);
    $this->situational = Portal::question($this->attempt, 1, 19);
});

it('puts knowledge questions first and situational questions after them', function (): void {
    expect($this->knowledge->typeCode())->toBe('knowledge')->and($this->knowledge->isSingle())->toBeTrue()
        ->and($this->situational->typeCode())->toBe('situational')->and($this->situational->isSingle())->toBeFalse();
});

describe('knowledge question (one correct answer)', function (): void {
    it('earns 1 point for the correct answer', function (): void {
        Portal::answer($this->knowledge, true);

        expect($this->knowledge->fresh()->points())->toBe(1)->and($this->knowledge->maxPoints())->toBe(1);
    });

    it('earns nothing for a wrong answer', function (): void {
        Portal::answer($this->knowledge, false);

        expect($this->knowledge->fresh()->points())->toBe(0)->and($this->knowledge->fresh()->isAnswered())->toBeTrue();
    });

    it('earns nothing and is not answered when left blank', function (): void {
        expect($this->knowledge->points())->toBe(0)->and($this->knowledge->isAnswered())->toBeFalse();
    });
});

describe('situational question (best and worst answer)', function (): void {
    it('earns 2 points when both answers are right', function (): void {
        Portal::answer($this->situational, true, true);

        expect($this->situational->fresh()->points())->toBe(2)->and($this->situational->maxPoints())->toBe(2);
    });

    it('earns 1 point for only the right best answer', function (): void {
        Portal::answer($this->situational, true, false);

        expect($this->situational->fresh()->points())->toBe(1);
    });

    it('earns 1 point for only the right worst answer', function (): void {
        Portal::answer($this->situational, false, true);

        expect($this->situational->fresh()->points())->toBe(1);
    });

    it('earns nothing when both answers are wrong', function (): void {
        Portal::answer($this->situational, false, false);

        expect($this->situational->fresh()->points())->toBe(0);
    });

    it('is not complete with only a best answer', function (): void {
        $o = Portal::options($this->situational);
        ExamResponse::query()->create(['exam_attempt_question_id' => $this->situational->id, 'best_answer_option_id' => $o['best'], 'worst_answer_option_id' => null]);

        $fresh = $this->situational->fresh();

        expect($fresh->isAnswered())->toBeFalse()->and($fresh->points())->toBe(0);
    });
});

it('uses the points frozen into the attempt, not the current database values', function (): void {
    $this->knowledge->update(['question_snapshot' => array_merge($this->knowledge->snapshot(), ['best_points' => 5])]);
    Portal::answer($this->knowledge->fresh(), true);

    expect($this->knowledge->fresh()->points())->toBe(5)->and($this->knowledge->fresh()->maxPoints())->toBe(5);
});

it('treats an old snapshot without a mode as a best/worst question worth 1 + 1', function (): void {
    $snapshot = $this->situational->snapshot();
    unset($snapshot['mode'], $snapshot['best_points'], $snapshot['worst_points'], $snapshot['type']);
    $this->situational->update(['question_snapshot' => $snapshot]);
    $fresh = $this->situational->fresh();

    expect($fresh->mode())->toBe('best_worst')->and($fresh->maxPoints())->toBe(2)->and($fresh->typeCode())->toBe('situational');
});

it('copes with an empty snapshot', function (): void {
    $this->knowledge->update(['question_snapshot' => []]);
    $fresh = $this->knowledge->fresh();

    expect($fresh->snapshot())->toBe([])
        ->and($fresh->snapshotOptions())->toHaveCount(0)
        ->and($fresh->bestOptionId())->toBeNull()
        ->and($fresh->worstOptionId())->toBeNull()
        ->and($fresh->points())->toBe(0);
});

it('finds the correct option ids in the snapshot', function (): void {
    $o = Portal::options($this->situational);

    expect($this->situational->bestOptionId())->toBe($o['best'])
        ->and($this->situational->worstOptionId())->toBe($o['worst'])
        ->and($this->knowledge->worstOptionId())->toBeNull();
});
