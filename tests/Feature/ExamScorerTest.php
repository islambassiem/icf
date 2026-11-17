<?php

use App\Models\QuestionType;
use App\Services\Exams\ExamScorer;
use Tests\Support\Portal;

beforeEach(function (): void {
    [$this->user, $this->attempt] = Portal::startedExam();
    $this->scorer = resolve(ExamScorer::class);
});

/** Answers every question of the exam with the given rules. */
function answerEverything($attempt, Closure $rule): void
{
    foreach ($attempt->attemptQuestions()->get() as $question) {
        [$best, $worst] = $rule($question);
        Portal::answer($question, $best, $worst);
    }
}

it('has 124 points available: 36 knowledge + 44 situational x 2', function (): void {
    expect($this->scorer->maxPoints($this->attempt))->toBe(124);
});

it('scores 100% when every answer is right', function (): void {
    answerEverything($this->attempt, fn (): array => [true, true]);

    expect($this->scorer->points($this->attempt))->toBe(124)->and($this->scorer->score($this->attempt))->toBe(100);
});

it('scores 0 when every answer is wrong', function (): void {
    answerEverything($this->attempt, fn (): array => [false, false]);

    expect($this->scorer->points($this->attempt))->toBe(0)->and($this->scorer->score($this->attempt))->toBe(0);
});

it('scores 0 when nothing was answered', function (): void {
    expect($this->scorer->score($this->attempt))->toBe(0);
});

it('counts only the knowledge answers when the situational ones are wrong (36 of 124)', function (): void {
    answerEverything($this->attempt, fn ($q): array => [$q->isSingle(), false]);

    expect($this->scorer->points($this->attempt))->toBe(36)->and($this->scorer->score($this->attempt))->toBe(29);
});

it('gives half credit when only the best answer is right in situational questions (80 of 124)', function (): void {
    answerEverything($this->attempt, fn (): array => [true, false]);

    expect($this->scorer->points($this->attempt))->toBe(80)->and($this->scorer->score($this->attempt))->toBe(65);
});

it('counts unanswered questions as zero but keeps them in the total (62 of 124)', function (): void {
    Portal::answerPart($this->attempt, 1);   // part 1 right, part 2 left blank

    expect($this->scorer->points($this->attempt))->toBe(62)->and($this->scorer->score($this->attempt))->toBe(50);
});

it('reports on the configured score scale', function (): void {
    config(['portal.score_max' => 160]);
    answerEverything($this->attempt, fn (): array => [true, true]);

    expect($this->scorer->score($this->attempt))->toBe(160);
});

it('is not changed when the question type points are edited after the exam started', function (): void {
    QuestionType::query()->where('code', 'knowledge')->update(['best_points' => 9]);
    answerEverything($this->attempt, fn (): array => [true, true]);

    expect($this->scorer->maxPoints($this->attempt->fresh()))->toBe(124)->and($this->scorer->score($this->attempt->fresh()))->toBe(100);
});

it('uses the new points for an exam started afterwards', function (): void {
    answerEverything($this->attempt, fn (): array => [true, true]);
    $this->attempt->update(['completed_at' => now()]);
    QuestionType::query()->where('code', 'knowledge')->update(['best_points' => 2]);

    $second = Portal::start($this->user);

    expect($second->maxPoints())->toBe(36 * 2 + 44 * 2);
});
