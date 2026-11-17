<?php

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentEvent;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Date;
use Tests\Support\Gateways;
use Tests\Support\Portal;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-11-16 10:00:00'));
    $this->user = Portal::user();
    $this->plan = Portal::plan(['price' => 99, 'duration_days' => 30]);
    $this->service = resolve(PaymentService::class);
});

afterEach(fn () => Date::setTestNow());

describe('starting a checkout', function (): void {
    it('creates a pending subscription and a pending payment, and activates nothing', function (): void {
        [$payment, $session] = $this->service->startCheckout($this->user, $this->plan);

        $subscription = $payment->subscription;

        expect($payment->statusKey())->toBe('pending')
            ->and((float) $payment->amount)->toBe(99.0)
            ->and($payment->currencyCode())->toBe('SAR')
            ->and($payment->paid_at)->toBeNull()
            ->and($payment->provider)->toBe('fake')
            ->and($payment->provider_checkout_id)->toBe($session->id)
            ->and($session->id)->toStartWith('fake_chk_')
            ->and($session->url)->toBe(route('fake-gateway.show', $payment))
            ->and($subscription->status)->toBe(Subscription::STATUS_PENDING)
            ->and($subscription->user_id)->toBe($this->user->id)
            ->and($subscription->starts_at->equalTo(now()))->toBeTrue()
            ->and($subscription->ends_at->equalTo(now()->addDays(30)))->toBeTrue()
            ->and((float) $subscription->amount_paid)->toBe(0.0)
            ->and($this->user->activeSubscription()->first())->toBeNull();
    });

    it('takes the price from the plan on the server', function (): void {
        $expensive = Portal::plan(['name_en' => 'Yearly', 'price' => 799.50, 'duration_days' => 365]);

        [$payment] = $this->service->startCheckout($this->user, $expensive);

        expect((float) $payment->amount)->toBe(799.5);
    });

    it('starts the new period when the running plan ends', function (): void {
        Portal::subscribe($this->user, ['ends_at' => now()->addDays(10)]);

        [$payment] = $this->service->startCheckout($this->user, $this->plan);

        expect($payment->subscription->starts_at->equalTo(now()->addDays(10)))->toBeTrue()
            ->and($payment->subscription->ends_at->equalTo(now()->addDays(40)))->toBeTrue();
    });

    it('does not create a second pending payment when the user clicks pay twice', function (): void {
        [$first] = $this->service->startCheckout($this->user, $this->plan);
        [$second] = $this->service->startCheckout($this->user, $this->plan);

        expect($second->id)->toBe($first->id)->and(Payment::query()->count())->toBe(1)->and(Subscription::query()->count())->toBe(1);
    });

    it('keeps different plans and different users apart', function (): void {
        $other = Portal::plan(['name_en' => 'Quarterly']);

        $this->service->startCheckout($this->user, $this->plan);
        $this->service->startCheckout($this->user, $other);
        $this->service->startCheckout(Portal::user(), $this->plan);

        expect(Payment::query()->count())->toBe(3);
    });

    it('does not reuse a pending payment that is too old', function (): void {
        $this->service->startCheckout($this->user, $this->plan);
        $this->travel(31)->minutes();

        $this->service->startCheckout($this->user, $this->plan);

        expect(Payment::query()->count())->toBe(2);
    });

    it('does not reuse a payment that already failed', function (): void {
        [$payment, $session] = $this->service->startCheckout($this->user, $this->plan);
        $this->service->applyEvent(new PaymentEvent($session->id, 'failed', null, 'declined'));

        [$retry] = $this->service->startCheckout($this->user, $this->plan);

        expect($retry->id)->not->toBe($payment->id)->and($retry->statusKey())->toBe('pending');
    });

    it('marks the payment failed and rethrows when the provider is down', function (): void {
        app()->bind(PaymentGateway::class, fn (): PaymentGateway => Gateways::down());

        expect(fn () => resolve(PaymentService::class)->startCheckout($this->user, $this->plan))->toThrow(RuntimeException::class);

        $payment = Payment::query()->firstOrFail();

        expect($payment->statusKey())->toBe('failed')
            ->and($payment->failure_reason)->toBe('gateway_error')
            ->and($payment->subscription->status)->toBe(Subscription::STATUS_FAILED)
            ->and($this->user->activeSubscription()->first())->toBeNull();
    });
});

describe('a paid event', function (): void {
    beforeEach(function (): void {
        [$this->payment, $this->session] = $this->service->startCheckout($this->user, $this->plan);
        $this->paid = new PaymentEvent($this->session->id, 'paid', 'fake_pay_1');
    });

    it('activates the plan and records the payment', function (): void {
        $this->service->applyEvent($this->paid);

        $payment = $this->payment->fresh();
        $subscription = $payment->subscription;

        expect($payment->statusKey())->toBe('paid')
            ->and($payment->paid_at->equalTo(now()))->toBeTrue()
            ->and($payment->provider_payment_id)->toBe('fake_pay_1')
            ->and($payment->failure_reason)->toBeNull()
            ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
            ->and((float) $subscription->amount_paid)->toBe(99.0)
            ->and($subscription->starts_at->equalTo(now()))->toBeTrue()
            ->and($subscription->ends_at->equalTo(now()->addDays(30)))->toBeTrue()
            ->and($this->user->activeSubscription()->first()?->id)->toBe($subscription->id);
    });

    it('can be applied again without changing anything (providers resend webhooks)', function (): void {
        $this->service->applyEvent($this->paid);
        $first = $this->payment->fresh();

        $this->travel(5)->minutes();
        $this->service->applyEvent($this->paid);
        $again = $this->payment->fresh();

        expect($again->paid_at->equalTo($first->paid_at))->toBeTrue()
            ->and($again->subscription->ends_at->equalTo($first->subscription->ends_at))->toBeTrue()
            ->and(Payment::query()->count())->toBe(1)->and(Subscription::query()->count())->toBe(1);
    });

    it('moves the period after another plan that became active in the meantime', function (): void {
        Portal::subscribe($this->user, ['ends_at' => now()->addDays(20)]);

        $this->service->applyEvent($this->paid);

        $subscription = $this->payment->fresh()->subscription;

        expect($subscription->starts_at->equalTo(now()->addDays(20)))->toBeTrue()
            ->and($subscription->ends_at->equalTo(now()->addDays(50)))->toBeTrue();
    });

    it('still activates the plan if the money arrives after a failure was recorded', function (): void {
        $this->service->applyEvent(new PaymentEvent($this->session->id, 'failed', null, 'declined'));
        $this->service->applyEvent($this->paid);

        expect($this->payment->fresh()->statusKey())->toBe('paid')
            ->and($this->payment->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
    });

    it('never changes a refunded payment', function (): void {
        try {
            $refunded = Portal::paymentStatus('refunded');
        } catch (RuntimeException) {
            $this->markTestSkipped('PaymentStatusEnum has no "refunded" case.');
        }

        $this->payment->update(['status' => $refunded]);
        $this->service->applyEvent($this->paid);

        expect($this->payment->fresh()->statusKey())->toBe('refunded');
    });
});

describe('a failed event', function (): void {
    beforeEach(function (): void {
        [$this->payment, $this->session] = $this->service->startCheckout($this->user, $this->plan);
    });

    it('records the reason and gives no access', function (): void {
        $this->service->applyEvent(new PaymentEvent($this->session->id, 'failed', null, 'insufficient_funds'));

        $payment = $this->payment->fresh();

        expect($payment->statusKey())->toBe('failed')
            ->and($payment->failure_reason)->toBe('insufficient_funds')
            ->and($payment->subscription->status)->toBe(Subscription::STATUS_FAILED)
            ->and($this->user->activeSubscription()->first())->toBeNull();
    });

    it('uses "declined" when the provider gives no reason', function (): void {
        $this->service->applyEvent(new PaymentEvent($this->session->id, 'failed'));

        expect($this->payment->fresh()->failure_reason)->toBe('declined');
    });

    it('cannot undo a payment that was already paid', function (): void {
        $this->service->applyEvent(new PaymentEvent($this->session->id, 'paid', 'pay_1'));
        $this->service->applyEvent(new PaymentEvent($this->session->id, 'failed', null, 'declined'));

        expect($this->payment->fresh()->statusKey())->toBe('paid')
            ->and($this->payment->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
    });
});

it('ignores an event for a checkout it does not know', function (): void {
    $result = $this->service->applyEvent(new PaymentEvent('fake_chk_unknown', 'paid', 'x'));

    expect($result)->toBeNull()->and(Payment::query()->count())->toBe(0);
});

describe('next start date', function (): void {
    it('is now when the user has no running plan', function (): void {
        expect($this->service->nextStart($this->user)->equalTo(now()))->toBeTrue();
    });

    it('is the end of the running plan', function (): void {
        Portal::subscribe($this->user, ['ends_at' => now()->addDays(12)]);

        expect($this->service->nextStart($this->user)->equalTo(now()->addDays(12)))->toBeTrue();
    });

    it('ignores plans that are not paid', function (): void {
        Portal::subscribe($this->user, ['status' => Subscription::STATUS_PENDING, 'ends_at' => now()->addDays(12)]);

        expect($this->service->nextStart($this->user)->equalTo(now()))->toBeTrue();
    });
});
