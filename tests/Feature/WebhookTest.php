<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentService;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user();
    [$this->payment, $this->session] = resolve(PaymentService::class)->startCheckout($this->user, Portal::plan(['price' => 99]));
    $this->payload = ['checkout_id' => $this->session->id, 'payment_id' => 'fake_pay_1', 'status' => 'paid', 'reason' => null];
});

describe('happy path', function (): void {
    it('activates the plan for a correctly signed "paid" webhook, with no login', function (): void {
        Portal::webhook($this, $this->payload)->assertOk()->assertJson(['ok' => true]);

        expect($this->payment->fresh()->statusKey())->toBe('paid')
            ->and($this->payment->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE)
            ->and($this->user->activeSubscription()->first())->not->toBeNull();
    });

    it('records a "failed" webhook with its reason', function (): void {
        Portal::webhook($this, ['status' => 'failed', 'reason' => 'declined'] + $this->payload)->assertOk();

        expect($this->payment->fresh()->statusKey())->toBe('failed')->and($this->payment->fresh()->failure_reason)->toBe('declined');
    });

    it('is safe to receive twice', function (): void {
        Portal::webhook($this, $this->payload)->assertOk();
        $paidAt = $this->payment->fresh()->paid_at;

        $this->travel(10)->minutes();
        Portal::webhook($this, $this->payload)->assertOk();

        expect($this->payment->fresh()->paid_at->equalTo($paidAt))->toBeTrue()->and(Payment::query()->count())->toBe(1);
    });

    it('answers 200 for a checkout it does not know, so the provider stops retrying', function (): void {
        Portal::webhook($this, ['checkout_id' => 'fake_chk_nope'] + $this->payload)->assertOk();

        expect($this->payment->fresh()->statusKey())->toBe('pending');
    });
});

describe('sad path', function (): void {
    it('rejects a wrong signature and changes nothing', function (): void {
        Portal::webhook($this, $this->payload, 'not-the-right-signature')->assertStatus(400);

        expect($this->payment->fresh()->statusKey())->toBe('pending')->and($this->user->activeSubscription()->first())->toBeNull();
    });

    it('rejects a body that was changed after it was signed', function (): void {
        $signature = hash_hmac('sha256', json_encode($this->payload), (string) config('payments.fake.secret'));

        Portal::webhook($this, ['status' => 'paid', 'payment_id' => 'tampered'] + $this->payload, $signature)->assertStatus(400);

        expect($this->payment->fresh()->statusKey())->toBe('pending');
    });

    it('rejects a request with no signature', function (): void {
        Portal::webhook($this, $this->payload, '')->assertStatus(400);

        expect($this->payment->fresh()->statusKey())->toBe('pending');
    });

    it('rejects a signed body that is not valid JSON', function (): void {
        Portal::webhook($this, [], null, 'this is not json')->assertStatus(400);
    });

    it('rejects a body without a checkout id', function (): void {
        Portal::webhook($this, ['checkout_id' => null] + $this->payload)->assertStatus(400);
    });

    it('rejects a status it does not understand', function (): void {
        Portal::webhook($this, ['status' => 'refunded'] + $this->payload)->assertStatus(400);

        expect($this->payment->fresh()->statusKey())->toBe('pending');
    });

    it('ignores any amount sent in the body: the price always comes from the plan', function (): void {
        Portal::webhook($this, ['amount' => 1, 'currency' => 'USD'] + $this->payload)->assertOk();

        expect((float) $this->payment->fresh()->amount)->toBe(99.0)
            ->and((float) $this->payment->fresh()->subscription->amount_paid)->toBe(99.0);
    });

    it('cannot be used to change another payment than the one named by its checkout id', function (): void {
        [$other] = resolve(PaymentService::class)->startCheckout(Portal::user(), Portal::plan(['name_en' => 'Other']));

        Portal::webhook($this, $this->payload)->assertOk();

        expect($other->fresh()->statusKey())->toBe('pending');
    });
});
