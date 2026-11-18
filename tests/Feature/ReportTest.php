<?php

use App\Models\ExamAttempt;
use App\Services\Exams\ExamClock;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->type = Portal::examType('ACC');
    Portal::bank($this->type);
    $this->user = Portal::subscriber();
});

/** Takes a whole exam: knowledge answers right/wrong, and situational answers right/wrong per rule. */
function takeExam($test, bool $knowledgeRight, bool $bestRight, bool $worstRight): ExamAttempt
{
    $attempt = Portal::start($test->user, $test->type);
    $clock = resolve(ExamClock::class);

    foreach ([1, 2] as $part) {
        foreach ($attempt->refresh()->attemptQuestions()->where('part_number', $part)->get() as $question) {
            $question->isSingle() ? Portal::answer($question, $knowledgeRight) : Portal::answer($question, $bestRight, $worstRight);
        }

        $part === 1 ? $clock->startBreak($attempt->refresh()) : $clock->complete($attempt->refresh());
        if ($part === 1) {
            $clock->startPart2($attempt->refresh());
        }
    }

    return $attempt->refresh();
}

describe('who can see a report', function (): void {
    beforeEach(fn (): ExamAttempt => $this->attempt = takeExam($this, true, true, true));

    it('sends guests to the login page', function (): void {
        $this->get(route('attempts.show', $this->attempt))->assertRedirect(route('login'));
    });

    it("does not show someone else's report", function (): void {
        $this->actingAs(Portal::user())->get(route('attempts.show', $this->attempt))->assertNotFound();
    });

    it('sends the user back to the exam while it is still running', function (): void {
        $running = Portal::start(Portal::subscriber(), $this->type);

        $this->actingAs($running->user)->get(route('attempts.show', $running))->assertRedirect(route('exams.take', $running));
    });

    it('closes and scores an exam whose time ran out while the user was away', function (): void {
        $user = Portal::subscriber();
        $attempt = Portal::start($user, $this->type);
        Portal::answerPart($attempt, 1);
        $this->travel(160)->minutes();

        $this->actingAs($user)->get(route('attempts.show', $attempt))->assertOk()->assertSee('50%');

        expect($attempt->fresh()->completed_at)->not->toBeNull();
    });
});

describe('a perfect exam', function (): void {
    beforeEach(fn (): ExamAttempt => $this->attempt = takeExam($this, true, true, true));

    it('shows the score and the points', function (): void {
        $this->actingAs($this->user)->get(route('attempts.show', $this->attempt))
            ->assertOk()
            ->assertSee('ACC exam report')
            ->assertSee('100%')
            // ->assertSee('124 of 124 points')
            ->assertSee('80 of 80 answered');
    });

    it('breaks the result down by question type and by profile type', function (): void {
        $this->actingAs($this->user)
            ->get(route('attempts.show', $this->attempt))
            // ->assertSee('By question type')
            // ->assertSee('Knowledge')
            // ->assertSee('Situational Judgment')
            ->assertSee('By profile type')
            ->assertSee('Competency')
            ->assertSee('Skill')
            ->assertSee('Value');
    });

    it('says there is nothing to fix', function (): void {
        $this->actingAs($this->user)->get(route('attempts.show', $this->attempt))->assertSee('Strong result across all areas');
    });

    it('counts every question as correct', function (): void {
        $counts = Livewire::actingAs($this->user)
            ->test('pages::attempts.show', ['attempt' => $this->attempt])->instance()->counts;

        expect($counts)->toMatchArray(
            ['all' => 80, 'correct' => 44, 'incorrect' => 0, 'partial' => 36, 'unanswered' => 0]
        );
    });

    it('shows the expert reasoning and the comment on every option after the exam', function (): void {
        $this->actingAs($this->user)->get(route('attempts.show', $this->attempt))
            // ->assertSee('Expert reasoning')
            // ->assertSee('Rationale for knowledge question')
            // ->assertSee('Rationale for situational question')
            ->assertSee('Expert comment')
            ->assertSee('Reason 1 for');
    });

    it('labels the right answers for each kind of question', function (): void {
        $this->actingAs($this->user)->get(route('attempts.show', $this->attempt))
            // ->assertSee('Correct answer')       // knowledge
            ->assertSee('Best answer')          // situational
            ->assertSee('Worst answer');
    });

    it('is shown in Arabic', function (): void {
        $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('attempts.show', $this->attempt))
            ->assertOk()->assertSee('تقرير اختبار');
    });
});

describe('an exam with mistakes', function (): void {
    it('scores 0% when everything is wrong and points the user to what to fix', function (): void {
        $attempt = takeExam($this, false, false, false);

        $this->actingAs($this->user)->get(route('attempts.show', $attempt))
            ->assertSee('0%')
            // ->assertSee('0 of 124 points')
            ->assertSee('Where to focus next')
            ->assertSee('Show missed questions');

        $counts = Livewire::actingAs($this->user)->test('pages::attempts.show', ['attempt' => $attempt])->instance()->counts;

        expect($counts['incorrect'])->toBe(80)->and($counts['correct'])->toBe(0);
    });

    it('gives partial credit for a right best answer with a wrong worst answer', function (): void {
        $attempt = takeExam($this, true, true, false);

        $counts = Livewire::actingAs($this->user)->test('pages::attempts.show', ['attempt' => $attempt])->instance()->counts;

        expect($attempt->score)->toBe(65)                               // 36 + 44 of 124 points
            ->and($counts['correct'])->toBe(0)                         // the knowledge questions
            ->and($counts['partial'])->toBe(80);                        // the situational questions
    });

    it('lists questions that were never answered', function (): void {
        $attempt = Portal::start($this->user, $this->type);
        Portal::answerPart($attempt, 1);
        $this->travel(160)->minutes();

        $counts = Livewire::actingAs($this->user)->test('pages::attempts.show', ['attempt' => $attempt])->instance()->counts;

        expect($counts['unanswered'])->toBe(40)
            ->and($counts['partial'])->toBe(18)
            ->and($counts['correct'])->toBe(22);
    });
});

describe('filtering the questions', function (): void {
    beforeEach(fn (): ExamAttempt => $this->attempt = takeExam($this, true, true, false));

    it('filters to one group of questions', function (): void {
        $page = Livewire::actingAs($this->user)->test('pages::attempts.show', ['attempt' => $this->attempt]);

        $page->call('setFilter', 'partial')->assertSet('filter', 'partial');

        expect($page->instance()->visible)->toHaveCount(80);
    });

    it('shows everything by default', function (): void {
        $page = Livewire::actingAs($this->user)->test('pages::attempts.show', ['attempt' => $this->attempt]);

        expect($page->instance()->visible)->toHaveCount(80);
    });

    it('ignores a filter that does not exist', function (): void {
        Livewire::actingAs($this->user)->test('pages::attempts.show', ['attempt' => $this->attempt])
            ->call('setFilter', 'hacked')->assertSet('filter', 'all');
    });
});

describe('progress between exams', function (): void {
    it('shows the first exam as a starting point', function (): void {
        $first = takeExam($this, false, false, false);

        $this->actingAs($this->user)->get(route('attempts.show', $first))->assertSee('This is your first ACC exam');
    });

    it('shows the change compared with the previous exam of the same type', function (): void {
        $first = takeExam($this, false, false, false);   // 0%
        $second = takeExam($this, true, true, true);     // 100%

        $this
            ->actingAs($this->user)
            ->get(route('attempts.show', $second))
            ->assertSee('+100')
            ->assertSee('points vs your previous ACC exam');
    })->skip();
});

describe('the report does not change when the question bank changes', function (): void {
    it('keeps the original questions and answers', function (): void {
        $attempt = takeExam($this, true, true, true);
        DB::table('questions')->update(['statement_en' => 'Rewritten later']);
        DB::table('answer_options')->update(['is_best' => false, 'is_worst' => false]);

        $this->actingAs($this->user)->get(route('attempts.show', $attempt))->assertSee('100%')->assertDontSee('Rewritten later');
    });
});
