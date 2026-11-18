<?php

use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user();   // password is "password"
});

describe('the page', function (): void {
    it('sends guests to the login page', function (): void {
        $this->get(route('security.edit'))->assertRedirect(route('login'));
    });

    it('asks the user to confirm their password first', function (): void {
        $this->actingAs($this->user)->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    });

    it('opens once the password was confirmed', function (): void {
        $this->actingAs($this->user)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))
            ->assertOk()->assertSee('Change password');
    });

    it('is shown in Arabic', function (): void {
        $this->actingAs($this->user)->withSession(['auth.password_confirmed_at' => time(), 'locale' => 'ar'])->get(route('security.edit'))
            ->assertOk()->assertSee('تغيير كلمة المرور');
    });
});

describe('changing the password', function (): void {
    it('saves a new password when the current one is right', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.security')
            ->set('current_password', 'password')
            ->set('password', 'N3w-Strong-Passw0rd!')->set('password_confirmation', 'N3w-Strong-Passw0rd!')
            ->call('updatePassword')
            ->assertHasNoErrors()->assertDispatched('password-updated')
            ->assertSet('password', '');

        expect(Hash::check('N3w-Strong-Passw0rd!', $this->user->fresh()->password))->toBeTrue();
    });

    it('refuses a wrong current password and clears the fields', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.security')
            ->set('current_password', 'wrong')
            ->set('password', 'N3w-Strong-Passw0rd!')->set('password_confirmation', 'N3w-Strong-Passw0rd!')
            ->call('updatePassword')
            ->assertHasErrors(['current_password'])
            ->assertSet('password', '')->assertSet('current_password', '');

        expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
    });

    it('refuses a confirmation that does not match', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.security')
            ->set('current_password', 'password')
            ->set('password', 'N3w-Strong-Passw0rd!')->set('password_confirmation', 'something else')
            ->call('updatePassword')->assertHasErrors(['password']);

        expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
    });

    it('refuses a password that is too short', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.security')
            ->set('current_password', 'password')->set('password', 'short')->set('password_confirmation', 'short')
            ->call('updatePassword')->assertHasErrors(['password']);
    });

    it('refuses an empty new password', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.security')
            ->set('current_password', 'password')->call('updatePassword')->assertHasErrors(['password']);
    });
});

describe('two-factor authentication', function (): void {
    beforeEach(function (): void {
        if (! Features::canManageTwoFactorAuthentication() || ! Fortify::confirmsTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication with confirmation is not enabled in config/fortify.php.');
        }
    });

    $validCode = fn ($user) => (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));

    it('starts the setup: creates a secret and shows the dialog', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->dispatch('start-two-factor-setup')
            ->assertSet('open', true)
            ->assertSet('showVerificationStep', false);

        expect($this->user->fresh()->two_factor_secret)->not->toBeNull()->and($this->user->fresh()->two_factor_confirmed_at)->toBeNull();
    });

    it('moves to the code step', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->dispatch('start-two-factor-setup')->call('showVerificationIfNecessary')->assertSet('showVerificationStep', true);
    });

    it('turns two-factor on with a correct code', function () use ($validCode): void {
        $page = Livewire::actingAs($this->user)->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->dispatch('start-two-factor-setup')->call('showVerificationIfNecessary');

        $page->set('code', $validCode($this->user))->call('confirmTwoFactor')
            ->assertHasNoErrors()->assertDispatched('two-factor-enabled')->assertSet('open', false);

        expect($this->user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    });

    it('does not turn it on with a wrong code', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->dispatch('start-two-factor-setup')->call('showVerificationIfNecessary')
            ->set('code', '000000')->call('confirmTwoFactor')->assertHasErrors(['code']);

        expect($this->user->fresh()->two_factor_confirmed_at)->toBeNull();
    });

    it('needs a 6-digit code', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->dispatch('start-two-factor-setup')->call('showVerificationIfNecessary')
            ->set('code', '123')->call('confirmTwoFactor')->assertHasErrors(['code']);
    });

    it('closes the dialog and forgets the code', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
            ->dispatch('start-two-factor-setup')->set('code', '123456')->call('closeModal')
            ->assertSet('open', false)->assertSet('code', '');
    });

    it('turns two-factor off', function (): void {
        $this->user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();

        Livewire::actingAs($this->user)->test('pages::settings.security')->assertSet('twoFactorEnabled', true)
            ->call('disable')->assertSet('twoFactorEnabled', false);

        expect($this->user->fresh()->two_factor_secret)->toBeNull();
    });

    it('throws away a setup that was started but never confirmed', function (): void {
        $this->user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => null])->save();

        Livewire::actingAs($this->user)->test('pages::settings.security')->assertSet('twoFactorEnabled', false);

        expect($this->user->fresh()->two_factor_secret)->toBeNull();
    });

    it('shows the recovery codes and can create new ones', function (): void {
        $this->user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-1111', 'bbbb-2222'])), 'two_factor_confirmed_at' => now()])->save();

        $page = Livewire::actingAs($this->user)->test('pages::settings.two-factor.recovery-codes')->assertSee('aaaa-1111')->assertSee('bbbb-2222');
        $before = $this->user->fresh()->two_factor_recovery_codes;

        $page->call('regenerateRecoveryCodes')->assertDontSee('aaaa-1111');

        expect($this->user->fresh()->two_factor_recovery_codes)->not->toBe($before);
    });
});

describe('passkeys', function (): void {
    it('shows an empty list when the user has none', function (): void {
        if (! Features::canManagePasskeys()) {
            $this->markTestSkipped('Passkeys are not enabled in config/fortify.php.');
        }

        Livewire::actingAs($this->user)->test('pages::settings.security')->assertSee('No passkeys yet.');
    });
});
