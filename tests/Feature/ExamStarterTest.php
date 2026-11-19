<?php

use App\Exceptions\ExamNotConfiguredException;
use App\Exceptions\InsufficientQuestionsException;
use App\Exceptions\NoActiveSubscriptionException;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\ProfileType;
use App\Services\Exams\ExamStarter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\Support\Portal;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-11-16 10:00:00'));
    $this->type = Portal::examType();
    Portal::bank($this->type);                 // 40 knowledge + 50 situational (an exam needs 36 + 44)
    $this->user = Portal::subscriber();
    $this->starter = resolve(ExamStarter::class);
});

afterEach(fn () => Date::setTestNow());

describe('who may start an exam', function (): void {
    it('lets a user with an active subscription start', function (): void {
        expect($this->starter->start($this->user, $this->type))->toBeInstanceOf(ExamAttempt::class);
    });

    it('refuses a user who never subscribed', function (): void {
        $this->starter->start(Portal::user(), $this->type);
    })->throws(NoActiveSubscriptionException::class);

    it('refuses a user whose subscription cannot be used', function (array $attributes): void {
        $user = Portal::user();

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($attributes[$field]) && is_int($attributes[$field])) {
                $attributes[$field] = now()->addDays($attributes[$field]);
            }
        }

        Portal::subscribe($user, $attributes);

        expect(fn () => $this->starter->start($user, $this->type))
            ->toThrow(NoActiveSubscriptionException::class)
            ->and(ExamAttempt::query()->count())->toBe(0);
    })->with([
        'payment still pending' => [['status' => 'pending']],
        'payment failed' => [['status' => 'failed']],
        'canceled' => [['status' => 'canceled']],
        'ended yesterday' => [['ends_at' => -1]],
        'not started yet' => [
            ['starts_at' => 1, 'ends_at' => 31],
        ],
    ]);

    it('links the attempt to the subscription it was started with', function (): void {
        $attempt = $this->starter->start($this->user, $this->type);

        expect($attempt->subscription_id)->toBe($this->user->activeSubscription()->first()->id)
            ->and($attempt->user_id)->toBe($this->user->id)
            ->and($attempt->exam_type_id)->toBe($this->type->id);
    });
});

describe('the exam it builds', function (): void {
    beforeEach(fn () => $this->attempt = $this->starter->start($this->user, $this->type));

    it('has 80 questions: 40 in each part', function (): void {
        expect($this->attempt->attemptQuestions()->count())->toBe(80)
            ->and($this->attempt->attemptQuestions()->where('part_number', 1)->count())->toBe(40)
            ->and($this->attempt->attemptQuestions()->where('part_number', 2)->count())->toBe(40);
    });

    it('puts 18 knowledge questions first, then 22 situational ones, in each part', function (): void {
        foreach ([1, 2] as $part) {
            $types = $this->attempt->attemptQuestions()->where('part_number', $part)->orderBy('position')->get()->map->typeCode();

            expect($types->take(18)->unique()->all())->toBe(['knowledge'])
                ->and($types->slice(18)->unique()->values()->all())->toBe(['situational'])
                ->and($types)->toHaveCount(40);
        }
    });

    it('numbers the questions 1 to 40 in each part', function (): void {
        foreach ([1, 2] as $part) {
            $positions = $this->attempt->attemptQuestions()->where('part_number', $part)->orderBy('position')->pluck('position')->all();

            expect($positions)->toBe(range(1, 40));
        }
    });

    it('never uses a question twice in the same exam', function (): void {
        $ids = $this->attempt->attemptQuestions()->pluck('question_id');

        expect($ids->unique())->toHaveCount(80);
    });

    it('freezes the timing and the points into the attempt', function (): void {
        expect($this->attempt->started_at->equalTo(now()))->toBeTrue()
            ->and($this->attempt->part_1_ends_at->equalTo(now()->addMinutes(70)))->toBeTrue()
            ->and($this->attempt->expires_at->equalTo(now()->addMinutes(150)))->toBeTrue()
            ->and($this->attempt->structure()['total_questions'])->toBe(80)
            ->and($this->attempt->maxPoints())->toBe(124);
    });

    it('freezes the question text, all options and the right answers', function (): void {
        $question = Portal::question($this->attempt, 1, 19);   // first situational question
        $snapshot = $question->snapshot();

        expect($snapshot['mode'])->toBe('best_worst')
            ->and($snapshot['statement_en'])->toContain('Situational question')
            ->and($snapshot['rationale_en'])->toContain('Rationale')
            ->and($question->snapshotOptions())->toHaveCount(4)
            ->and($question->bestOptionId())->not->toBeNull()
            ->and($question->worstOptionId())->not->toBeNull();
    });

    it('is not affected by later edits to the question bank', function (): void {
        $question = Portal::question($this->attempt, 1, 1);
        $before = $question->snapshot()['statement_en'];

        DB::table('questions')->where('id', $question->question_id)->update(['statement_en' => 'Edited afterwards']);

        expect($question->fresh()->snapshot()['statement_en'])->toBe($before);
    });
});

describe('where the questions come from', function (): void {
    it('uses only the questions of the chosen exam type', function (): void {
        $pcc = Portal::examType('PCC');
        $pccIds = Portal::bank($pcc);

        $attempt = $this->starter->start($this->user, $this->type);

        expect($attempt->attemptQuestions()->pluck('question_id')->intersect($pccIds))->toHaveCount(0);
    });

    it('uses only published questions', function (): void {
        $drafts = Portal::bank($this->type, 10, 10, ['status' => 'draft']);

        $attempt = $this->starter->start($this->user, $this->type);

        expect($attempt->attemptQuestions()->pluck('question_id')->intersect($drafts))->toHaveCount(0);
    });

    it('uses every status when no status filter is configured', function (): void {
        config(['portal.question_status' => '']);
        DB::table('questions')->update(['status' => 'whatever']);

        expect($this->starter->start($this->user, $this->type)->attemptQuestions()->count())->toBe(80);
    });

    it('skips a knowledge question that has two correct answers or none', function (): void {
        $knowledge = Portal::questionType('knowledge');
        $bad = [];

        foreach ([[true, true, false, false], [false, false, false, false]] as $flags) {
            $id = DB::table('questions')->insertGetId([
                'statement_en' => 'Broken knowledge', 'statement_ar' => 'x', 'exam_type_id' => $this->type->id,
                'profile_type_id' => ProfileType::query()->first()->id, 'question_type_id' => $knowledge->id,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($flags as $i => $best) {
                DB::table('answer_options')->insert(['question_id' => $id, 'statement_en' => "o{$i}", 'statement_ar' => 'x', 'comment_en' => '', 'comment_ar' => '', 'is_best' => $best, 'is_worst' => false, 'created_at' => now(), 'updated_at' => now()]);
            }

            $bad[] = $id;
        }

        $attempt = $this->starter->start($this->user, $this->type);

        expect($attempt->attemptQuestions()->pluck('question_id')->intersect($bad))->toHaveCount(0);
    });

    it('skips a situational question that has no worst answer', function (): void {
        $situational = Portal::questionType('situational');
        $id = DB::table('questions')->insertGetId([
            'statement_en' => 'No worst answer', 'statement_ar' => 'x', 'exam_type_id' => $this->type->id,
            'profile_type_id' => ProfileType::query()->first()->id, 'question_type_id' => $situational->id,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([true, false] as $i => $best) {
            DB::table('answer_options')->insert(['question_id' => $id, 'statement_en' => "o{$i}", 'statement_ar' => 'x', 'comment_en' => '', 'comment_ar' => '', 'is_best' => $best, 'is_worst' => false, 'created_at' => now(), 'updated_at' => now()]);
        }

        $attempt = $this->starter->start($this->user, $this->type);

        expect($attempt->attemptQuestions()->pluck('question_id'))->not->toContain($id);
    });

    it('skips a question with fewer than two options', function (): void {
        $knowledge = Portal::questionType('knowledge');
        $id = DB::table('questions')->insertGetId([
            'statement_en' => 'One option only', 'statement_ar' => 'x', 'exam_type_id' => $this->type->id,
            'profile_type_id' => ProfileType::query()->first()->id, 'question_type_id' => $knowledge->id,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('answer_options')->insert(['question_id' => $id, 'statement_en' => 'only', 'statement_ar' => 'x', 'comment_en' => '', 'comment_ar' => '', 'is_best' => true, 'is_worst' => false, 'created_at' => now(), 'updated_at' => now()]);

        $attempt = $this->starter->start($this->user, $this->type);

        expect($attempt->attemptQuestions()->pluck('question_id'))->not->toContain($id);
    });

    it('does not mix up the two kinds of question', function (): void {
        $attempt = $this->starter->start($this->user, $this->type);

        $knowledgeIds = DB::table('questions')->where('question_type_id', Portal::questionType('knowledge')->id)->pluck('id');
        $inKnowledgeSlots = $attempt->attemptQuestions()->get()->filter(fn ($q): bool => $q->typeCode() === 'knowledge')->pluck('question_id');

        expect($inKnowledgeSlots->diff($knowledgeIds))->toHaveCount(0);
    });
});

describe('questions a user has seen before', function (): void {
    it('prefers questions the user has not seen', function (): void {
        $first = $this->starter->start($this->user, $this->type);
        $seen = $first->attemptQuestions()->pluck('question_id');
        Portal::completeExam($first);

        $second = $this->starter->start($this->user, $this->type);
        $unseen = DB::table('questions')->whereNotIn('id', $seen)->pluck('id');   // 4 knowledge + 6 situational

        expect($unseen)->toHaveCount(10)
            ->and($second->attemptQuestions()->pluck('question_id')->intersect($unseen))->toHaveCount(10);
    });

    it('reuses questions only when there are not enough new ones', function (): void {
        DB::table('answer_options')->delete();
        DB::table('questions')->delete();
        Portal::bank($this->type, 36, 44);          // exactly one exam's worth

        $first = $this->starter->start($this->user, $this->type);
        Portal::completeExam($first);
        $second = $this->starter->start($this->user, $this->type);

        expect($second->attemptQuestions()->pluck('question_id')->sort()->values()->all())
            ->toBe($first->attemptQuestions()->pluck('question_id')->sort()->values()->all());
    });

    it('treats every user separately', function (): void {
        $first = $this->starter->start($this->user, $this->type);
        Portal::completeExam($first);

        $other = Portal::subscriber();
        $second = $this->starter->start($other, $this->type);

        expect($second->attemptQuestions()->count())->toBe(80);
    });
});

describe('sad path', function (): void {
    it('continues the exam already running instead of starting another', function (): void {
        $first = $this->starter->start($this->user, $this->type);
        $second = $this->starter->start($this->user, $this->type);

        expect($second->id)->toBe($first->id)->and(ExamAttempt::query()->count())->toBe(1);
    });

    it('starts a new exam once the previous one is finished', function (): void {
        $first = $this->starter->start($this->user, $this->type);
        Portal::completeExam($first);

        expect($this->starter->start($this->user, $this->type)->id)->not->toBe($first->id);
    });

    it('fails clearly, creating nothing, when there are not enough knowledge questions', function (): void {
        DB::table('answer_options')->delete();
        DB::table('questions')->delete();
        Portal::bank($this->type, 30, 50);   // part 2 would need 18 but only 12 remain

        try {
            $this->starter->start($this->user, $this->type);
            $this->fail('Expected InsufficientQuestionsException');
        } catch (InsufficientQuestionsException $insufficientQuestionsException) {
            expect($insufficientQuestionsException->questionType)->toBe('Knowledge')->and($insufficientQuestionsException->available)->toBeLessThan($insufficientQuestionsException->needed);
        }

        expect(ExamAttempt::query()->count())->toBe(0);
    });

    it('fails clearly when there are not enough situational questions', function (): void {
        DB::table('answer_options')->delete();
        DB::table('questions')->delete();
        Portal::bank($this->type, 40, 30);

        expect(fn () => $this->starter->start($this->user, $this->type))->toThrow(InsufficientQuestionsException::class)
            ->and(ExamAttempt::query()->count())->toBe(0);
    });

    it('fails clearly for an exam type that has no structure', function (): void {
        $type = ExamType::query()->create(['name_en' => 'NEW', 'name_ar' => 'NEW']);

        expect(fn () => $this->starter->start($this->user, $type))->toThrow(ExamNotConfiguredException::class)
            ->and(ExamAttempt::query()->count())->toBe(0);
    });
});

describe('following the structure in the database', function (): void {
    it('builds a different exam when the structure is changed', function (): void {
        $type = Portal::examType('PCC', ['knowledge' => 5, 'situational' => 3, 'minutes' => 20, 'break' => 2]);
        Portal::bank($type);

        $attempt = $this->starter->start($this->user, $type);

        expect($attempt->attemptQuestions()->count())->toBe(2 * (5 + 3))
            ->and($attempt->part_1_ends_at->equalTo(now()->addMinutes(20)))->toBeTrue()
            ->and($attempt->expires_at->equalTo(now()->addMinutes(20 + 2 + 20)))->toBeTrue();
    });
});
