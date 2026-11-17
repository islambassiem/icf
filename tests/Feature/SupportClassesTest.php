<?php

declare(strict_types=1);

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Support\EnumPick;
use Tests\Support\Portal;

describe('EnumPick', function (): void {
    it('finds a case by value or name, ignoring letter case', function (): void {
        expect(EnumPick::case(PaymentStatusEnum::class, 'PAID'))->toBeInstanceOf(PaymentStatusEnum::class)
            ->and(EnumPick::case(PaymentStatusEnum::class, 'pending'))->toBeInstanceOf(PaymentStatusEnum::class)
            ->and(EnumPick::case(CurrencyEnum::class, 'sar'))->toBeInstanceOf(CurrencyEnum::class);
    });

    it('fails loudly for an unknown case instead of guessing', function (): void {
        EnumPick::case(PaymentStatusEnum::class, 'does-not-exist');
    })->throws(RuntimeException::class);
});

describe('Payment helpers', function (): void {
    it('reads the status as lower-case text', function (): void {
        $payment = Portal::payment(Portal::subscribe(Portal::user()), 'paid');

        expect($payment->statusKey())->toBe('paid');
    });

    it('reads the currency as an upper-case code', function (): void {
        $payment = Portal::payment(Portal::subscribe(Portal::user()), 'paid');

        expect($payment->currencyCode())->toBe('SAR');
    });

    it('lets pending and failed payments exist without a paid date', function (): void {
        $subscription = Portal::subscribe(Portal::user());

        expect(Portal::payment($subscription, 'pending')->paid_at)->toBeNull()
            ->and(Portal::payment($subscription, 'failed')->paid_at)->toBeNull();
    });
});
