<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentService;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user();
    $this->plan = Portal::plan(['name_en' => 'Quarterly', 'price' => 249]);
    [$this->payment] = resolve(PaymentService::class)->startCheckout($this->user, $this->plan);
});

describe('the provider page', function (): void {
    it('shows the amount and plan to the customer, clearly marked as a test', function (): void {
        $this->actingAs($this->user)->get(route('fake-gateway.show', $this->payment))
            ->assertOk()
            ->assertSee('TEST MODE')
            ->assertSee('249.00 SAR')
            ->assertSee('Quarterly');
    });

    it('sends a customer back to the portal if the payment is already finished', function (): void {
        $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'pay']);

        $this->actingAs($this->user)->get(route('fake-gateway.show', $this->payment))
            ->assertRedirect(route('subscription.return', $this->payment));
    });

    it('needs a login', function (): void {
        $this->get(route('fake-gateway.show', $this->payment))->assertRedirect(route('login'));
    });

    it("is not available to someone else's payment", function (): void {
        $this->actingAs(Portal::user())->get(route('fake-gateway.show', $this->payment))->assertNotFound();
    });
});

describe('happy path: paying', function (): void {
    it('pays, activates the plan and returns to the portal', function (): void {
        $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'pay'])
            ->assertRedirect(route('subscription.return', $this->payment));

        expect($this->payment->fresh()->statusKey())->toBe('paid')
            ->and($this->payment->fresh()->provider_payment_id)->toStartWith('fake_pay_')
            ->and($this->user->activeSubscription()->first())->not->toBeNull();
    });

    it('does not activate twice if the customer submits the page twice', function (): void {
        $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'pay']);
        $endsAt = $this->payment->fresh()->subscription->ends_at;

        $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'pay']);

        expect($this->payment->fresh()->subscription->ends_at->equalTo($endsAt))->toBeTrue()
            ->and(Subscription::query()->count())->toBe(1);
    });
});

describe('sad path: the payment does not go through', function (): void {
    // it('records why it failed and gives no access', function (string $outcome): void {
    //     $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), ['outcome' => $outcome])
    //         ->assertRedirect(route('subscription.return', $this->payment));

    //     expect($this->payment->fresh()->statusKey())->toBe('failed')
    //         ->and($this->payment->fresh()->failure_reason)->toBe($outcome)
    //         ->and($this->user->activeSubscription()->first())->toBeNull();
    // })->with(['declined', 'insufficient_funds', 'canceled']);

    it('rejects an outcome that does not exist and leaves the payment pending', function (): void {
        $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'free-money'])
            ->assertSessionHasErrors('outcome');

        expect($this->payment->fresh()->statusKey())->toBe('pending');
    });

    it('rejects a missing outcome', function (): void {
        $this->actingAs($this->user)->post(route('fake-gateway.complete', $this->payment), [])->assertSessionHasErrors('outcome');
    });

    it("cannot complete someone else's payment", function (): void {
        $this->actingAs(Portal::user())->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'pay'])->assertNotFound();

        expect($this->payment->fresh()->statusKey())->toBe('pending');
    });

    it('needs a login to complete a payment', function (): void {
        $this->post(route('fake-gateway.complete', $this->payment), ['outcome' => 'pay'])->assertRedirect(route('login'));

        expect(Payment::query()->first()->statusKey())->toBe('pending');
    });
});
