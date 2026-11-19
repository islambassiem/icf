<?php

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Tests\Support\Portal;

/*
 * Every private page must send guests to the login page, and the pages that need a verified email
 * must send unverified users to the verification page.
 */

it('sends guests to the login page from every private page', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'dashboard' => 'dashboard',
    'subscription' => 'subscription.index',
    'my attempts' => 'attempts.index',
    'profile' => 'profile.edit',
    'language' => 'appearance.edit',
    'security' => 'security.edit',
    'exams' => 'exams.index',
]);

it('sends guests to the login page from every private page that belongs to one item', function (): void {
    $user = Portal::subscriber();
    $type = Portal::examType();
    Portal::bank($type);
    $attempt = Portal::start($user, $type);
    $payment = Portal::payment($user->subscriptions()->first(), 'paid');
    $plan = Portal::plan(['name_en' => 'Other']);

    foreach ([
        route('subscription.checkout', $plan),
        route('subscription.return', $payment),
        route('payments.receipt', $payment),
        route('exams.take', $attempt),
        route('attempts.show', $attempt),
        route('fake-gateway.show', $payment),
    ] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

it('keeps users with an unverified email out of the pages that need verification', function (string $route): void {
    $user = Portal::user(['email_verified_at' => null]);

    if (! $user instanceof MustVerifyEmail) {
        $this->markTestSkipped('MustVerifyEmail is not enabled');
    }

    $this
        ->actingAs($user)
        ->get(route($route))
        ->assertRedirect(route('verification.notice'));
})->with(['dashboard', 'subscription.index', 'attempts.index', 'appearance.edit']);

it('lets users with an unverified email reach their profile to fix their address', function (): void {
    $user = Portal::user(['email_verified_at' => null]);

    if (! $user instanceof MustVerifyEmail) {
        $this->markTestSkipped('MustVerifyEmail is not enabled');
    }

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
});

it('keeps every item private to its owner', function (): void {
    $owner = Portal::subscriber();
    $type = Portal::examType();
    Portal::bank($type);
    $attempt = Portal::start($owner, $type);
    $payment = Portal::payment($owner->subscriptions()->first(), 'paid');
    $intruder = Portal::user();

    foreach ([
        route('subscription.return', $payment),
        route('payments.receipt', $payment),
        route('exams.take', $attempt),
        route('attempts.show', $attempt),
        route('fake-gateway.show', $payment),
    ] as $url) {
        $this->actingAs($intruder)->get($url)->assertNotFound();
    }
});

it('keeps the payment webhook open to the provider, with no login', function (): void {
    Portal::webhook($this, ['checkout_id' => 'x', 'status' => 'paid'])->assertOk();
});
