<?php

use App\Models\User;
use Tests\Support\Portal;

it('sends guests to the login page', function (): void {
    $this->get(route('appearance.edit'))->assertRedirect(route('login'));
});

it('sends users with an unverified email to the verification page', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    if (! $user instanceof MustVerifyEmail) {
        $this->markTestSkipped('MustVerifyEmail is not enabled');
    }

    $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertRedirect(route('verification.notice'));
});

it('offers both languages, each switching through the language route', function (): void {
    $this->actingAs(Portal::user())->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('English')->assertSee('العربية')
        ->assertSee(route('locale.switch', 'en'))
        ->assertSee(route('locale.switch', 'ar'));
});

it('marks the language in use', function (): void {
    $this->actingAs(Portal::user())->get(route('appearance.edit'))->assertSee('Current');
    $this->actingAs(Portal::user())->withSession(['locale' => 'ar'])->get(route('appearance.edit'))->assertSee('الحالية');
});
