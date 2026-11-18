<?php

use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->type = Portal::examType('ACC');
    Portal::bank($this->type);
    $this->plan = Portal::plan(['name_en' => 'Quarterly', 'price' => 249]);
    $this->user = Portal::user(['name' => 'Layla Hassan']);
});

describe('who can open it', function (): void {
    it('sends guests to the login page', function (): void {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    });

    it('sends users with an unverified email to the verification page', function (): void {

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        if (! $user instanceof MustVerifyEmail) {
            $this->markTestSkipped('MustVerifyEmail is not enabled');
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    });

    it('greets the user by first name', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('Layla');
    });

    it('is shown in Arabic', function (): void {
        $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertOk()->assertSee('لوحة التحكم');
    });
});

describe('a user without a subscription', function (): void {
    it('is invited to subscribe and sees the plans', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Subscribe to unlock your mock exams')
            ->assertSee('Choose your plan')
            ->assertSee('Quarterly')
            ->assertSee(route('subscription.checkout', $this->plan));
    });

    it('cannot start an exam: the start button is replaced by a lock', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('An active subscription is needed to start an exam.')
            ->assertDontSee('Start ACC exam');
    });

    it('is told so when it tries to start anyway, and no exam is created', function (): void {
        Livewire::actingAs($this->user)->test('pages::dashboard')
            ->call('startExam')
            ->assertSee('An active subscription is needed to start an exam.')
            ->assertNoRedirect();

        expect(ExamAttempt::query()->count())->toBe(0);
    });

    it('is not helped by an expired subscription', function (): void {
        Portal::subscribe($this->user, ['plan' => $this->plan, 'starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(10)]);

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Expired')
            ->assertSee('Ended on')
            ->assertSee('An active subscription is needed to start an exam.');
    });
});

describe('a subscribed user', function (): void {
    beforeEach(fn (): Subscription => Portal::subscribe($this->user, ['plan' => $this->plan]));

    it('sees the plan status and the exam that can be started', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Active')
            ->assertSee('29 days left')
            ->assertSee('Start ACC exam')
            ->assertSee('Sit your first mock exam')
            ->assertDontSee('An active subscription is needed');
    });

    it('sees the real exam structure from the database', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('2 parts')
            ->assertSee('18 Knowledge in each part')
            ->assertSee('22 Situational Judgment in each part')
            ->assertSee('70 minutes per part')
            ->assertSee('10 minute break');
    });

    it('sees a changed structure straight away', function (): void {
        $pcc = Portal::examType('PCC', ['knowledge' => 10, 'situational' => 5, 'minutes' => 45, 'break' => 5]);

        Livewire::actingAs($this->user)->test('pages::dashboard')
            ->call('setExamType', $pcc->id)
            ->assertSee('10 Knowledge in each part')
            ->assertSee('5 Situational Judgment in each part')
            ->assertSee('45 minutes per part')
            ->assertSee('5 minute break');
    });

    it('only suggests renewing when the plan is about to end', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee('Choose your plan');

        $ending = Portal::user();
        Portal::subscribe($ending, ['plan' => $this->plan, 'ends_at' => now()->addDays(5)]);

        $this->actingAs($ending)->get(route('dashboard'))->assertSee('Your plan ends soon')->assertSee('Choose your plan');
    });
});

describe('starting an exam', function (): void {
    beforeEach(fn (): Subscription => Portal::subscribe($this->user, ['plan' => $this->plan]));

    it('creates the exam and opens it', function (): void {
        Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam');

        $attempt = ExamAttempt::query()->firstOrFail();

        expect($attempt->user_id)->toBe($this->user->id)->and($attempt->attemptQuestions()->count())->toBe(80);
    });

    it('redirects to the exam page', function (): void {
        $page = Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam');

        $page->assertRedirect(route('exams.take', ExamAttempt::query()->firstOrFail()));
    });

    it('continues the running exam instead of starting another', function (): void {
        $page = Livewire::actingAs($this->user)->test('pages::dashboard');
        $page->call('startExam');
        $page->call('startExam');

        expect(ExamAttempt::query()->count())->toBe(1);
    });

    it('shows a resume panel while an exam is running', function (): void {
        $attempt = Portal::start($this->user, $this->type);
        Portal::answerPart($attempt, 1, only: 12);

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Exam in progress')
            ->assertSee('12 of 80 answered')
            ->assertSee('You have an exam in progress')
            ->assertSee(route('exams.take', $attempt));
    });

    it('says the exam is not available when there are not enough questions, and creates nothing', function (): void {
        DB::table('answer_options')->delete();
        DB::table('questions')->delete();
        Portal::bank($this->type, 10, 10);

        Livewire::actingAs($this->user)->test('pages::dashboard')
            ->call('startExam')
            ->assertSee('does not have enough questions yet')
            ->assertNoRedirect();

        expect(ExamAttempt::query()->count())->toBe(0);
    });

    it('says the exam is not available when its structure is not set up', function (): void {
        $unconfigured = ExamType::query()->create(['name_en' => 'EMPTY', 'name_ar' => 'EMPTY']);

        Livewire::actingAs($this->user)->test('pages::dashboard')
            ->call('setExamType', $unconfigured->id)
            ->assertSee('does not have enough questions yet')
            ->call('startExam')
            ->assertNoRedirect();

        expect(ExamAttempt::query()->count())->toBe(0);
    });

    it('ignores an exam type that does not exist', function (): void {
        Livewire::actingAs($this->user)->test('pages::dashboard')
            ->assertSet('examTypeId', $this->type->id)
            ->call('setExamType', 999999)
            ->assertSet('examTypeId', $this->type->id);
    });
});

describe('results', function (): void {
    beforeEach(fn (): Subscription => Portal::subscribe($this->user, ['plan' => $this->plan]));

    it('shows scores, results by type and the next step after a finished exam', function (): void {
        $attempt = Portal::start($this->user, $this->type);
        Portal::completeExam($attempt, false, false);     // everything wrong

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Exams completed')
            ->assertSee('Where to focus')
            ->assertSee('By question type')
            ->assertSee('Knowledge')
            ->assertSee('Work on');
    });

    it('shows the best score', function (): void {
        Portal::completeExam(Portal::start($this->user, $this->type));

        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('100%');
    });

    it('shows an empty state before the first exam', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('No exams yet.')
            ->assertSee('Complete an exam to see your progress here.');
    });
});

describe('payments on the dashboard', function (): void {
    it('offers to try again for a failed payment and a receipt for a paid one', function (): void {
        $subscription = Portal::subscribe($this->user, ['plan' => $this->plan]);
        $paid = Portal::payment($subscription, 'paid');
        Portal::payment($subscription, 'failed');

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Failed')
            ->assertSee('Try again')
            ->assertSee(route('payments.receipt', $paid));
    });

    it('shows an empty state when there are no payments', function (): void {
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('No payments yet.');
    });
});
