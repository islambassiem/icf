<?php

use App\Contracts\PaymentGateway;
use Livewire\Livewire;
use Tests\Support\Gateways;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user();
    $this->plan = Portal::plan(['name_en' => 'Quarterly', 'price' => 249]);
    $this->subscription = Portal::subscribe($this->user, ['plan' => $this->plan, 'status' => 'pending', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
});

describe('who can open it', function (): void {
    it('sends guests to the login page', function (): void {
        $payment = Portal::payment($this->subscription, 'pending');

        $this->get(route('subscription.return', $payment))->assertRedirect(route('login'));
    });

    it("does not show another customer's payment", function (): void {
        $payment = Portal::payment($this->subscription, 'paid');

        $this->actingAs(Portal::user())->get(route('subscription.return', $payment))->assertNotFound();
    });
});

describe('pending', function (): void {
    it('asks the customer to wait and keeps checking', function (): void {
        $payment = Portal::payment($this->subscription, 'pending');

        $this->actingAs($this->user)->get(route('subscription.return', $payment))
            ->assertOk()
            ->assertSee('Confirming your payment')
            ->assertSee('wire:poll', false);
    });

    it('explains the delay if it takes unusually long', function (): void {
        $payment = Portal::payment($this->subscription, 'pending', ['created_at' => now()->subMinutes(5)]);

        $this->actingAs($this->user)->get(route('subscription.return', $payment))->assertSee('taking longer than usual');
    });

    it('does not say that for a payment that was only just started', function (): void {
        $payment = Portal::payment($this->subscription, 'pending');

        $this->actingAs($this->user)->get(route('subscription.return', $payment))->assertDontSee('taking longer than usual');
    });

    it('asks the provider directly when the webhook is late, and then shows success', function (): void {
        app()->bind(PaymentGateway::class, fn (): PaymentGateway => Gateways::lateWebhook());
        $payment = Portal::payment($this->subscription, 'pending');

        $page = Livewire::actingAs($this->user)->test('pages::subscription.return', ['payment' => $payment]);
        $page->call('refresh')->call('refresh');
        expect($payment->fresh()->statusKey())->toBe('pending');   // not yet: it waits a few checks first

        $page->call('refresh');

        expect($payment->fresh()->statusKey())->toBe('paid')
            ->and($payment->fresh()->subscription->status)->toBe('active');
    });
});

describe('happy path: paid', function (): void {
    it('shows a success page with the details and the next steps', function (): void {
        $payment = Portal::payment($this->subscription, 'paid');

        $this->actingAs($this->user)->get(route('subscription.return', $payment))
            ->assertOk()
            ->assertSee('Payment successful')
            ->assertSee('Quarterly')
            ->assertSee('249 SAR')
            ->assertSee(route('payments.receipt', $payment))
            ->assertSee('Start an exam')
            ->assertDontSee('wire:poll', false);
    });

    it('is shown in Arabic', function (): void {
        $payment = Portal::payment($this->subscription, 'paid');

        $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('subscription.return', $payment))
            ->assertSee('تمت عملية الدفع بنجاح');
    });
});

describe('sad path: failed', function (): void {
    it('says the payment was not completed, with a way to try again', function (): void {
        $payment = Portal::payment($this->subscription, 'failed', ['failure_reason' => 'declined']);

        $this->actingAs($this->user)->get(route('subscription.return', $payment))
            ->assertOk()
            ->assertSee('Payment was not completed')
            ->assertSee('No charge was made')
            ->assertSee(route('subscription.checkout', $this->plan))
            ->assertDontSee('Payment successful');
    });

    it('explains each reason in plain words', function (string $reason, string $message): void {
        $payment = Portal::payment($this->subscription, 'failed', ['failure_reason' => $reason]);

        $this->actingAs($this->user)->get(route('subscription.return', $payment))->assertSee($message);
    })->with([
        'declined' => ['declined', 'Your card was declined'],
        'no money' => ['insufficient_funds', 'insufficient funds'],
        'canceled' => ['canceled', 'The payment was canceled'],
        'provider down' => ['gateway_error', 'could not reach the payment provider'],
        'unknown code' => ['some_new_code', 'The payment could not be completed'],
    ]);

    it('shows a readable provider message as it is', function (): void {
        $payment = Portal::payment($this->subscription, 'failed', ['failure_reason' => 'Card blocked by the bank']);

        $this->actingAs($this->user)->get(route('subscription.return', $payment))->assertSee('Card blocked by the bank');
    });

    it('does not show the paid-only buttons', function (): void {
        $payment = Portal::payment($this->subscription, 'failed');

        $this->actingAs($this->user)->get(route('subscription.return', $payment))->assertDontSee('View receipt');
    });
});
