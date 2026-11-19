<?php

use App\Models\ExamAttempt;
use App\Models\ExamResponse;
use App\Models\Payment;
use Livewire\Livewire;
use Tests\Support\Portal;

/*
 * Whole stories, told through the real pages: from "I have no plan" to "I read my report".
 */

beforeEach(function (): void {
    $this->type = Portal::examType('ACC');
    Portal::bank($this->type);
    $this->plan = Portal::plan(['name_en' => 'Quarterly', 'price' => 249, 'duration_days' => 90]);
    $this->user = Portal::user(['name' => 'Layla Hassan']);
});

/** Goes through checkout and the provider page; $outcome is what the customer does there. */
function payFor($test, string $outcome): Payment
{
    Livewire::actingAs($test->user)->test('pages::subscription.checkout', ['plan' => $test->plan])
        ->set('acceptTerms', true)->call('pay')->assertRedirectContains('fake-gateway');

    $payment = Payment::query()->latest('id')->firstOrFail();

    $test->actingAs($test->user)->get(route('fake-gateway.show', $payment))->assertOk();
    $test->actingAs($test->user)->post(route('fake-gateway.complete', $payment), ['outcome' => $outcome])
        ->assertRedirect(route('subscription.return', $payment));

    return $payment->fresh();
}

it('HAPPY PATH: from no plan to a finished exam and its report', function (): void {
    // 1. A new user sees that a plan is needed.
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('Subscribe to unlock your mock exams')
        ->assertSee('An active subscription is needed to start an exam.');

    // 2. Pays successfully.
    $payment = payFor($this, 'pay');
    $this->actingAs($this->user)->get(route('subscription.return', $payment))->assertSee('Payment successful');
    expect($this->user->activeSubscription()->first())->not->toBeNull();

    // 3. The dashboard unlocks and shows the receipt.
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertDontSee('An active subscription is needed to start an exam.')
        ->assertSee('Start ACC exam')
        ->assertSee(route('payments.receipt', $payment));
    $this->actingAs($this->user)->get(route('payments.receipt', $payment))->assertOk()->assertSee('249.00 SAR');

    // 4. Starts the exam from the dashboard.
    Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam');
    $attempt = ExamAttempt::query()->firstOrFail();
    expect($attempt->attemptQuestions()->count())->toBe(80);

    // 5. Answers a few questions on the real exam page.
    $page = Livewire::actingAs($this->user)->test('pages::exams.take', ['attempt' => $attempt]);
    $knowledge = Portal::question($attempt, 1, 1);
    $page->call('saveAnswer', $knowledge->id, Portal::options($knowledge)['best'], 0);
    $situational = Portal::question($attempt, 1, 19);
    $o = Portal::options($situational);
    $page->call('saveAnswer', $situational->id, $o['best'], $o['worst']);

    // 6. Cannot submit part 1 with gaps.
    $page->call('submitPart');

    expect($attempt->fresh()->break_started_at)->toBeNull();

    // 7. Finishes part 1, rests, does part 2.
    Portal::answerPart($attempt, 1);
    $page->call('submitPart')->assertSee('Break time');
    // $page->call('startPart2')->assertSee('Part 2 of 2');
    Portal::answerPart($attempt->fresh(), 2);
    $page
        ->call('submitPart')
        ->assertSee('Exam completed')
        ->assertSee('100%');

    // 8. Reads the report.
    $this->actingAs($this->user)->get(route('attempts.show', $attempt))
        ->assertOk()->assertSee('100%')->assertSee('124 of 124 points')->assertSee('Expert reasoning');

    // 9. The dashboard and the attempts list now show the result.
    $this->actingAs($this->user)->get(route('dashboard'))->assertSee('100%')->assertSee('Exams completed');
    $this->actingAs($this->user)->get(route('attempts.index'))->assertSee('Completed')->assertSee(route('attempts.show', $attempt));
})->skip();

it('SAD PATH: a declined payment gives no access, and a retry then works', function (): void {
    // 1. The card is declined.
    $failed = payFor($this, 'declined');
    $this->actingAs($this->user)->get(route('subscription.return', $failed))
        ->assertSee('Payment was not completed')->assertSee('Your card was declined')->assertSee(route('subscription.checkout', $this->plan));

    // 2. Nothing was unlocked.
    expect($this->user->activeSubscription()->first())->toBeNull();
    $this->actingAs($this->user)->get(route('dashboard'))->assertSee('An active subscription is needed to start an exam.')->assertSee('Try again');

    // 3. Starting an exam anyway is refused.
    Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam')->assertNoRedirect();
    expect(ExamAttempt::query()->count())->toBe(0);

    // 4. Trying again works.
    $retry = payFor($this, 'pay');
    expect($retry->id)->not->toBe($failed->id)->and($this->user->activeSubscription()->first())->not->toBeNull();

    Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam')->assertRedirect(route('exams.take', ExamAttempt::query()->firstOrFail()));
});

it('SAD PATH: cancelling at the provider and insufficient funds are treated as failures', function (): void {
    foreach (['canceled', 'insufficient_funds'] as $outcome) {
        $payment = payFor($this, $outcome);

        expect($payment->statusKey())->toBe('failed')->and($payment->failure_reason)->toBe($outcome);
    }

    expect($this->user->activeSubscription()->first())->toBeNull();
});

it('SAD PATH: abandoning the exam still gives a report, scored on the answers given', function (): void {
    Portal::subscribe($this->user, ['plan' => $this->plan]);
    $attempt = Portal::start($this->user, $this->type);

    foreach (range(1, 5) as $position) {
        Portal::answer(Portal::question($attempt, 1, $position));      // five knowledge answers, all right
    }

    $this->travel(200)->minutes();

    $this->actingAs($this->user)->get(route('attempts.show', $attempt))
        ->assertOk()->assertSee('4%')->assertSee('5 of 124 points')->assertSee('Not answered');

    expect($attempt->fresh()->completed_at)->not->toBeNull();

    // A new exam can be started afterwards.
    expect(Portal::start($this->user, $this->type)->id)->not->toBe($attempt->id);
})->skip();

it('SAD PATH: a plan that ends during an exam does not stop that exam, but blocks the next one', function (): void {
    $subscription = Portal::subscribe($this->user, ['plan' => $this->plan]);
    $attempt = Portal::start($this->user, $this->type);

    $subscription->update(['ends_at' => now()->subMinute()]);     // the plan runs out mid-exam

    $page = Livewire::actingAs($this->user)->test('pages::exams.take', ['attempt' => $attempt]);
    $knowledge = Portal::question($attempt, 1, 1);
    $page->call('saveAnswer', $knowledge->id, Portal::options($knowledge)['best'], 0);
    expect(ExamResponse::query()->count())->toBe(1);

    Portal::completeExam($attempt->fresh());
    $this->actingAs($this->user)->get(route('attempts.show', $attempt))->assertOk();

    Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam')->assertSee('An active subscription is needed');
    expect(ExamAttempt::query()->count())->toBe(1);
});

it("SAD PATH: a customer cannot use another customer's paid plan", function (): void {
    $other = Portal::user();
    Portal::subscribe($other, ['plan' => $this->plan]);

    Livewire::actingAs($this->user)->test('pages::dashboard')->call('startExam')->assertNoRedirect();

    expect(ExamAttempt::query()->count())->toBe(0);
});
