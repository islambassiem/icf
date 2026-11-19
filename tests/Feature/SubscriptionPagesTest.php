<?php

use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user(['name' => 'Layla Hassan']);
    $this->plan = Portal::plan(['name_en' => 'Quarterly', 'price' => 249]);
});

describe('subscription page', function (): void {
    it('sends guests to the login page', function (): void {
        $this->get(route('subscription.index'))->assertRedirect(route('login'));
    });

    it('shows the running plan and how long is left', function (): void {
        Portal::subscribe($this->user, ['plan' => $this->plan, 'ends_at' => now()->addDays(20)]);

        $this->actingAs($this->user)->get(route('subscription.index'))
            ->assertOk()
            ->assertSee('Quarterly')
            ->assertSee('20 days left');
    });

    it('says so when there is no active plan', function (): void {
        $this->actingAs($this->user)->get(route('subscription.index'))->assertSee('You do not have an active plan.');
    });

    it('lists a plan that is bought but has not started yet as upcoming', function (): void {
        Portal::subscribe($this->user, ['ends_at' => now()->addDays(5)]);
        $next = Portal::plan(['name_en' => 'Yearly']);
        Portal::subscribe($this->user, ['plan' => $next, 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(370)]);

        $this->actingAs($this->user)->get(route('subscription.index'))->assertSee('Upcoming')->assertSee('Yearly');
    });

    it('offers only the plans that are switched on', function (): void {
        Portal::plan(['name_en' => 'Retired plan', 'is_active' => false]);

        $this->actingAs($this->user)->get(route('subscription.index'))->assertSee('Quarterly')->assertDontSee('Retired plan');
    });

    it('shows an empty history when there are no payments', function (): void {
        $this->actingAs($this->user)->get(route('subscription.index'))->assertSee('No payments yet.');
    });
});

describe('payment history', function (): void {
    it('shows each payment with the right action', function (): void {
        $subscription = Portal::subscribe($this->user, ['plan' => $this->plan]);
        $paid = Portal::payment($subscription, 'paid');
        $failed = Portal::payment($subscription, 'failed', ['failure_reason' => 'insufficient_funds']);
        $pending = Portal::payment($subscription, 'pending');

        $this->actingAs($this->user)->get(route('subscription.index'))
            ->assertSee('Paid')->assertSee('Failed')->assertSee('Pending')
            ->assertSee(route('payments.receipt', $paid))                      // receipt for the paid one
            ->assertSee(route('subscription.checkout', $this->plan))           // try again for the failed one
            ->assertSee('insufficient funds')                                  // and why it failed
            ->assertSee(route('subscription.return', $pending));               // check status of the pending one
    });

    it("never shows another customer's payments", function (): void {
        $mine = Portal::subscribe($this->user, ['plan' => $this->plan]);
        Portal::payment($mine, 'paid', ['provider_payment_id' => 'MINE-1']);

        $theirPlan = Portal::plan(['name_en' => 'Secret plan', 'price' => 12345]);
        $theirs = Portal::subscribe(Portal::user(), ['plan' => $theirPlan]);
        Portal::payment($theirs, 'paid');

        expect($this->user->payments)->toHaveCount(1);
        expect($this->user->payments->first()->provider_payment_id)->toBe('MINE-1');
        expect($this->user->payments->pluck('id'))->not->toContain($theirs->payments()->first()->id);

        $response = $this
            ->actingAs($this->user)
            ->get(route('subscription.index'));

        $html = $response->getContent();
        expect($html)->toContain($theirPlan->name);
    });
});

describe('receipt', function (): void {
    beforeEach(function (): void {
        $this->subscription = Portal::subscribe($this->user, ['plan' => $this->plan]);
    });

    it('shows a numbered receipt for a paid payment', function (): void {
        $payment = Portal::payment($this->subscription, 'paid', ['provider_payment_id' => 'fake_pay_777']);

        $this->actingAs($this->user)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee('Payment receipt')
            ->assertSee('R-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Layla Hassan')
            ->assertSee('Quarterly')
            ->assertSee('249.00 SAR')
            ->assertSee('fake_pay_777');
    });

    it('is not issued for a payment that was not paid', function (string $status): void {
        $payment = Portal::payment($this->subscription, $status);

        $this->actingAs($this->user)->get(route('payments.receipt', $payment))->assertNotFound();
    })->with(['pending', 'failed']);

    it('cannot be opened by another customer', function (): void {
        $payment = Portal::payment($this->subscription, 'paid');

        $this->actingAs(Portal::user())->get(route('payments.receipt', $payment))->assertNotFound();
    });

    it('needs a login', function (): void {
        $payment = Portal::payment($this->subscription, 'paid');

        $this->get(route('payments.receipt', $payment))->assertRedirect(route('login'));
    });
});
