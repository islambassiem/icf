<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user(['name' => 'Layla Hassan', 'email' => 'layla@example.com']);
});

describe('the page', function (): void {
    it('sends guests to the login page', function (): void {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    });

    it('loads the current name and email', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')
            ->assertSet('name', 'Layla Hassan')
            ->assertSet('email', 'layla@example.com');
    });

    it('shows the settings tabs', function (): void {
        $this->actingAs($this->user)->get(route('profile.edit'))
            ->assertOk()->assertSee('Profile')->assertSee('Security')->assertSee('Language');
    });

    it('is shown in Arabic', function (): void {
        $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('profile.edit'))->assertOk()->assertSee('الملف الشخصي');
    });

    it('works for a user whose email is not verified yet', function (): void {
        $this->actingAs(Portal::user(['email_verified_at' => null]))->get(route('profile.edit'))->assertOk();
    });
});

describe('updating the details', function (): void {
    it('saves a new name and email', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')
            ->set('name', 'Layla H.')->set('email', 'new@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertDispatched('profile-updated');

        expect($this->user->fresh()->name)->toBe('Layla H.')->and($this->user->fresh()->email)->toBe('new@example.com');
    });

    it('keeps the email verified when only the name changes', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')
            ->set('name', 'Another Name')->call('updateProfileInformation')->assertHasNoErrors();

        expect($this->user->fresh()->email_verified_at)->not->toBeNull();
    });

    it('asks for the new email to be verified again when the address changes', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')
            ->set('email', 'changed@example.com')->call('updateProfileInformation');

        expect($this->user->fresh()->email_verified_at)->toBeNull();
    });

    it('does not mind saving the same email again', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')->call('updateProfileInformation')->assertHasNoErrors();
    });
});

describe('sad path: invalid details', function (): void {
    it('needs a name', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')->set('name', '')->call('updateProfileInformation')->assertHasErrors(['name']);

        expect($this->user->fresh()->name)->toBe('Layla Hassan');
    });

    it('needs a valid email', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')->set('email', 'not-an-email')->call('updateProfileInformation')->assertHasErrors(['email']);

        expect($this->user->fresh()->email)->toBe('layla@example.com');
    });

    it('does not accept an email that another user already has', function (): void {
        Portal::user(['email' => 'taken@example.com']);

        Livewire::actingAs($this->user)->test('pages::settings.profile')->set('email', 'taken@example.com')->call('updateProfileInformation')->assertHasErrors(['email']);
    });

    it('does not accept an absurdly long name', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.profile')->set('name', str_repeat('a', 300))->call('updateProfileInformation')->assertHasErrors(['name']);
    });
});

describe('email verification', function (): void {
    it('sends the verification email again on request', function (): void {
        Notification::fake();
        $unverified = Portal::user(['email_verified_at' => null]);

        Livewire::actingAs($unverified)->test('pages::settings.profile')->call('resendVerificationNotification');

        Notification::assertSentTo($unverified, VerifyEmail::class);
    });

    it('does not send anything to a user who is already verified', function (): void {
        Notification::fake();

        Livewire::actingAs($this->user)->test('pages::settings.profile')->call('resendVerificationNotification')->assertRedirect();

        Notification::assertNothingSent();
    });

    it('offers the delete option only to verified users', function (): void {
        $this
            ->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertSee('Delete my account');

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        if (! $user instanceof MustVerifyEmail) {
            $this->markTestSkipped('MustVerifyEmail is not enabled');
        }

        $this
            ->actingAs($user)
            ->get(route('profile.edit'))
            ->assertDontSee('Delete my account');
    });
});
