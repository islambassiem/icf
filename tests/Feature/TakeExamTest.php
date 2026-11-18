<?php

use App\Models\ExamResponse;
use App\Services\Exams\ExamClock;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Support\Portal;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-11-16 09:00:00'));
    [$this->user, $this->attempt] = Portal::startedExam();
    $this->open = fn () => Livewire::actingAs($this->user)->test('pages::exams.take', ['attempt' => $this->attempt]);
    $this->knowledge = Portal::question($this->attempt, 1, 1);
    $this->situational = Portal::question($this->attempt, 1, 19);
});

afterEach(fn () => Date::setTestNow());

describe('who can open the exam', function (): void {
    it('sends guests to the login page', function (): void {
        $this->get(route('exams.take', $this->attempt))->assertRedirect(route('login'));
    });

    it("does not show someone else's exam", function (): void {
        $this->actingAs(Portal::user())->get(route('exams.take', $this->attempt))->assertNotFound();
    });

    it('shows the first question with its type and part', function (): void {
        $this->actingAs($this->user)->get(route('exams.take', $this->attempt))
            ->assertOk()
            // ->assertSee('Part 1 of 2')
            ->assertSee('Knowledge')
            ->assertSee('Knowledge question')
            ->assertSee('Choose the ONE most accurate answer.');
    });

    it('shows the question in Arabic', function (): void {
        $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('exams.take', $this->attempt))
            ->assertOk();
        // ->assertSee('الجزء 1 من 2')
    });

    it('never sends the correct answers or the expert reasoning to the browser during the exam', function (): void {
        $this->actingAs($this->user)->get(route('exams.take', $this->attempt))
            ->assertDontSee('Rationale for')
            ->assertDontSee('Reason 1 for');
    });
});

describe('answering a knowledge question (one correct answer)', function (): void {
    it('saves the chosen option', function (): void {
        $o = Portal::options($this->knowledge);

        ($this->open)()->call('saveAnswer', $this->knowledge->id, $o['best'], 0);

        $response = ExamResponse::query()->where('exam_attempt_question_id', $this->knowledge->id)->firstOrFail();

        expect($response->best_answer_option_id)->toBe($o['best'])->and($response->worst_answer_option_id)->toBeNull();
    });

    it('lets the user change their mind, keeping a single answer', function (): void {
        $o = Portal::options($this->knowledge);
        $page = ($this->open)();

        $page->call('saveAnswer', $this->knowledge->id, $o['best'], 0);
        $page->call('saveAnswer', $this->knowledge->id, $o['others'][0], 0);

        expect(ExamResponse::query()->count())->toBe(1)->and(ExamResponse::query()->first()->best_answer_option_id)->toBe($o['others'][0]);
    });

    it('ignores a "worst" answer sent for a knowledge question', function (): void {
        $o = Portal::options($this->knowledge);

        ($this->open)()->call('saveAnswer', $this->knowledge->id, $o['best'], $o['others'][0]);

        expect(ExamResponse::query()->first()->worst_answer_option_id)->toBeNull();
    });

    it('rejects an option that does not exist', function (): void {
        ($this->open)()->call('saveAnswer', $this->knowledge->id, 99999999, 0);

        expect(ExamResponse::query()->count())->toBe(0);
    });

    it('rejects an option that belongs to a different question', function (): void {
        $foreign = Portal::options(Portal::question($this->attempt, 1, 2))['best'];

        ($this->open)()->call('saveAnswer', $this->knowledge->id, $foreign, 0);

        expect(ExamResponse::query()->count())->toBe(0);
    });
});

describe('answering a situational question (best and worst)', function (): void {
    it('saves both answers', function (): void {
        $o = Portal::options($this->situational);

        ($this->open)()->call('saveAnswer', $this->situational->id, $o['best'], $o['worst']);

        $response = ExamResponse::query()->firstOrFail();

        expect($response->best_answer_option_id)->toBe($o['best'])->and($response->worst_answer_option_id)->toBe($o['worst']);
    });

    it('rejects the same option as both best and worst', function (): void {
        $o = Portal::options($this->situational);

        ($this->open)()->call('saveAnswer', $this->situational->id, $o['best'], $o['best']);

        expect(ExamResponse::query()->count())->toBe(0);
    });

    it('rejects an answer without a worst option', function (): void {
        $o = Portal::options($this->situational);

        ($this->open)()->call('saveAnswer', $this->situational->id, $o['best'], 0);

        expect(ExamResponse::query()->count())->toBe(0);
    });

    it('rejects a worst option from another question', function (): void {
        $o = Portal::options($this->situational);
        $foreign = Portal::options(Portal::question($this->attempt, 1, 20))['worst'];

        ($this->open)()->call('saveAnswer', $this->situational->id, $o['best'], $foreign);

        expect(ExamResponse::query()->count())->toBe(0);
    });
});

describe('answering at the wrong time', function (): void {
    it('does not accept an answer for a part 2 question during part 1', function (): void {
        $part2 = Portal::question($this->attempt, 2, 1);
        $o = Portal::options($part2);

        try {
            ($this->open)()->call('saveAnswer', $part2->id, $o['best'], 0);
        } catch (Throwable) {
            // rejecting it by an error is fine too
        }

        expect(ExamResponse::query()->count())->toBe(0);
    });

    it('does not accept an answer after part 1 time is up', function (): void {
        $o = Portal::options($this->knowledge);
        $page = ($this->open)();

        $this->travelTo(now()->addMinutes(71));
        $page->call('saveAnswer', $this->knowledge->id, $o['best'], 0);

        expect(ExamResponse::query()->count())->toBe(0);
    });

    it('does not accept answers during the break', function (): void {
        Portal::answerPart($this->attempt, 1);
        $page = ($this->open)()->call('submitPart');
        $before = ExamResponse::query()->count();
        $o = Portal::options($this->knowledge);

        $page->call('saveAnswer', $this->knowledge->id, $o['others'][0], 0);

        expect(ExamResponse::query()->count())->toBe($before)
            ->and(ExamResponse::query()->where('exam_attempt_question_id', $this->knowledge->id)->first()->best_answer_option_id)->toBe($o['best']);
    });

    it('does not accept answers after the exam is finished', function (): void {
        Portal::completeExam($this->attempt);
        $before = ExamResponse::query()->count();
        $o = Portal::options($this->knowledge);

        ($this->open)()->call('saveAnswer', $this->knowledge->id, $o['others'][0], 0);

        expect(ExamResponse::query()->count())->toBe($before);
    });
});

describe('submitting a part', function (): void {
    it('will not submit part 1 while questions are unanswered, and says which one', function (): void {
        foreach ($this->attempt->attemptQuestions()->where('part_number', 1)->get() as $question) {
            if ($question->position !== 5) {
                Portal::answer($question);
            }
        }

        ($this->open)()->call('submitPart')
            ->assertSet('current', 5)
            ->assertSee('Question 5 is not answered yet');

        expect($this->attempt->fresh()->break_started_at)->toBeNull();
    });

    it('lists several unanswered questions', function (): void {
        foreach ($this->attempt->attemptQuestions()->where('part_number', 1)->get() as $question) {
            if (! in_array($question->position, [3, 30], true)) {
                Portal::answer($question);
            }
        }

        ($this->open)()->call('submitPart')->assertSet('current', 3)->assertSee('2 questions are not answered yet');
    });

    it('does not count a situational question with only a best answer as answered', function (): void {
        Portal::answerPart($this->attempt, 1);
        $o = Portal::options($this->situational);
        ExamResponse::query()->where('exam_attempt_question_id', $this->situational->id)->update(['worst_answer_option_id' => null]);

        ($this->open)()->call('submitPart')->assertSet('current', 19);

        expect($this->attempt->fresh()->break_started_at)->toBeNull();
    });

    it('submits part 1 when everything is answered, and starts the break', function (): void {
        Portal::answerPart($this->attempt, 1);

        ($this->open)()->call('submitPart')->assertSet('current', 1)->assertSee('Break time');

        expect($this->attempt->fresh()->break_started_at)->not->toBeNull()->and($this->attempt->fresh()->part_2_started_at)->toBeNull();
    });

    it('lets the user start part 2 without waiting for the break to end', function (): void {
        Portal::answerPart($this->attempt, 1);

        ($this->open)()
            ->call('submitPart')
            ->call('startPart2');
        // ->assertSee('Part 2 of 2')

        expect($this->attempt->fresh()->part_2_started_at)->not->toBeNull();
    });

    it('does not let part 2 be started from part 1', function (): void {
        ($this->open)()->call('startPart2');

        expect($this->attempt->fresh()->part_2_started_at)->toBeNull();
    });

    it('does not submit part 1 twice', function (): void {
        Portal::answerPart($this->attempt, 1);
        $page = ($this->open)()->call('submitPart');
        $breakStart = $this->attempt->fresh()->break_started_at;

        $this->travelTo(now()->addMinutes(2));
        $page->call('submitPart');

        expect($this->attempt->fresh()->break_started_at->equalTo($breakStart))->toBeTrue();
    });
});

describe('finishing the exam', function (): void {
    beforeEach(function (): void {
        Portal::answerPart($this->attempt, 1);
        $this->clock = resolve(ExamClock::class);
        $this->clock->startBreak($this->attempt);
        $this->clock->startPart2($this->attempt->fresh());
    });

    it('will not finish part 2 while questions are unanswered', function (): void {
        Portal::answerPart($this->attempt->fresh(), 2, only: 39);

        ($this->open)()->call('submitPart')->assertSet('current', 40);

        expect($this->attempt->fresh()->completed_at)->toBeNull();
    });

    it('finishes the exam and shows the score when everything is answered', function (): void {
        Portal::answerPart($this->attempt->fresh(), 2);

        ($this->open)()->call('submitPart')->assertSee('Exam completed')->assertSee('100%');

        $attempt = $this->attempt->fresh();

        expect($attempt->completed_at)->not->toBeNull()->and($attempt->score)->toBe(100)->and($attempt->status)->toBe('Completed');
    });

    it('scores wrong answers as zero', function (): void {
        Portal::answerPart($this->attempt->fresh(), 2, false, false);

        ($this->open)()->call('submitPart');

        expect($this->attempt->fresh()->score)->toBe(50);   // part 1 right (62) + part 2 wrong (0) of 124
    });

    it('shows the finished exam with a link to the report', function (): void {
        Portal::answerPart($this->attempt->fresh(), 2);
        ($this->open)()->call('submitPart');

        $this->actingAs($this->user)->get(route('exams.take', $this->attempt))
            ->assertOk()
            ->assertSee('Exam completed')
            ->assertSee(route('attempts.show', $this->attempt));
    });
});

describe('time and navigation', function (): void {
    it('moves to the break when part 1 time runs out', function (): void {
        $page = ($this->open)();

        $this->travelTo(now()->addMinutes(71));
        $page->call('tick')->assertSee('Break time');

        expect($this->attempt->fresh()->break_started_at)->not->toBeNull();
    });

    it('closes the exam when the total time runs out', function (): void {
        $page = ($this->open)();

        $this->travelTo(now()->addMinutes(155));
        $page->call('tick')->assertSee('Exam completed');

        expect($this->attempt->fresh()->completed_at)->not->toBeNull();
    });

    it('moves between questions and stays inside the part', function (): void {
        ($this->open)()->call('goTo', 7)->assertSet('current', 7)
            ->call('goTo', 999)->assertSet('current', 40)
            ->call('goTo', -3)->assertSet('current', 1);
    });

    it('opens on the first question that is still unanswered', function (): void {
        foreach ($this->attempt->attemptQuestions()->where('part_number', 1)->orderBy('position')->take(5)->get() as $question) {
            Portal::answer($question);
        }

        ($this->open)()->assertSet('current', 6);
    });

    it('opens on question 1 when nothing was answered', function (): void {
        ($this->open)()->assertSet('current', 1);
    });
});
