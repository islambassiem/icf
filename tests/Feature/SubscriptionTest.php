<?php

use App\Models\Subscription;
use Illuminate\Support\Facades\Date;
use Tests\Support\Portal;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-11-16 10:00:00'));
    $this->user = Portal::user();
});

afterEach(fn () => Date::setTestNow());

describe('active scope', function (): void {
    it('includes a paid subscription running right now', function (): void {
        Portal::subscribe($this->user);

        expect(Subscription::active()->count())->toBe(1);
    });

    it('excludes every kind of subscription that cannot be used', function (array $attributes): void {
        $attributes = array_map(
            fn ($value) => $value instanceof Closure ? $value() : $value,
            $attributes
        );

        Portal::subscribe($this->user, $attributes);

        expect(Subscription::active()->count())->toBe(0);
    })->with([
        'still pending payment' => [['status' => Subscription::STATUS_PENDING]],
        'payment failed' => [['status' => Subscription::STATUS_FAILED]],
        'canceled' => [['status' => Subscription::STATUS_CANCELED]],
        'marked expired' => [['status' => Subscription::STATUS_EXPIRED]],
        'ended yesterday' => [['ends_at' => fn () => Date::now()->subDay()]],
        'ends this very second' => [['ends_at' => fn () => Date::now()]],
        'starts tomorrow' => [[
            'starts_at' => fn () => Date::now()->addDay(),
            'ends_at' => fn () => Date::now()->addDays(31),
        ]],
    ]);

    it('belongs to the right user', function (): void {
        Portal::subscribe($this->user);
        $other = Portal::user();

        expect($other->activeSubscription()->first())->toBeNull()->and($this->user->activeSubscription()->first())->not->toBeNull();
    });
});

describe('activeSubscription relation', function (): void {
    it('returns the one that ends last when several are running', function (): void {
        Portal::subscribe($this->user, ['ends_at' => now()->addDays(5)]);
        $later = Portal::subscribe($this->user, ['ends_at' => now()->addDays(40)]);

        expect($this->user->activeSubscription()->first()->id)->toBe($later->id);
    });

    it('is empty for a user who never subscribed', function (): void {
        expect($this->user->activeSubscription()->first())->toBeNull();
    });
});

describe('time left', function (): void {
    it('counts the days left, rounding up', function (): void {
        $subscription = Portal::subscribe($this->user, ['ends_at' => now()->addDays(10)->addHours(3)]);

        expect($subscription->daysLeft())->toBe(11);
    });

    it('never goes below zero', function (): void {
        $subscription = Portal::subscribe($this->user, ['starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(10)]);

        expect($subscription->daysLeft())->toBe(0);
    });

    it('shows how much of the paid period has passed', function (): void {
        $half = Portal::subscribe($this->user, ['starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(10)]);
        $over = Portal::subscribe($this->user, ['starts_at' => now()->subDays(20), 'ends_at' => now()->subDay()]);

        expect($half->elapsedPercent())->toBe(50)->and($over->elapsedPercent())->toBe(100);
    });
});
