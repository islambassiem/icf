<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\Portal;

describe('the pages', function (): void {
    it('opens the sign-in and sign-up pages for guests', function (string $route, string $form): void {
        $this->get(route($route))->assertOk()->assertSee(route($form));
    })->with([
        'login' => ['login', 'login.store'],
        'register' => ['register', 'register.store'],
        'forgot password' => ['password.request', 'password.email'],
    ]);

    it('opens the reset-password page from an email link', function (): void {
        $this->get(route('password.reset', ['token' => 'abc123']).'?email=layla@example.com')
            ->assertOk()->assertSee(route('password.update'))->assertSee('abc123');
    });

    it('shows the brand panel and the language button', function (): void {
        $this->get(route('login'))->assertSee('Practise today.')->assertSee('العربية');
    });

    it('is shown in Arabic, laid out right to left', function (): void {
        $this->withSession(['locale' => 'ar'])->get(route('login'))
            ->assertOk()->assertSee('dir="rtl"', false)->assertSee('أهلاً بعودتك');
    });

    it('keeps signed-in users away from the guest pages', function (): void {
        $this->actingAs(Portal::user())->get(route('login'))->assertRedirect();
    });

    it('shows the email verification page to a user who has not verified', function (): void {
        $this->actingAs(Portal::user(['email_verified_at' => null]))->get(route('verification.notice'))
            ->assertOk()->assertSee('Verify your email');
    });

    it('does not show the verification page to a verified user', function (): void {
        $this->actingAs(Portal::user())->get(route('verification.notice'))->assertRedirect();
    });

    it('asks a signed-in user to confirm their password', function (): void {
        $this->actingAs(Portal::user())->get(route('password.confirm'))->assertOk()->assertSee('Confirm your password');
    });

    it('does not show the confirm-password page to guests', function (): void {
        $this->get(route('password.confirm'))->assertRedirect(route('login'));
    });

    it('sends someone without a pending sign-in away from the two-factor page', function (): void {
        $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    });
});

describe('signing in', function (): void {
    it('signs in with the right email and password', function (): void {
        $user = Portal::user();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    });

    it('refuses a wrong password', function (): void {
        $user = Portal::user();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors();

        $this->assertGuest();
    });

    it('refuses an email that has no account', function (): void {
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'password'])->assertSessionHasErrors();

        $this->assertGuest();
    });

    it('needs both fields', function (): void {
        $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);
    });
});

describe('signing up', function (): void {
    $valid = ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!'];

    it('creates the account and signs the user in', function () use ($valid): void {
        $this->post(route('register.store'), $valid)->assertRedirect();

        expect(User::query()->where('email', 'new@example.com')->exists())->toBeTrue();
        $this->assertAuthenticated();
    });

    it('does not store the password in plain text', function () use ($valid): void {
        $this->post(route('register.store'), $valid);

        expect(Hash::check('Str0ng-Passw0rd!', User::query()->where('email', 'new@example.com')->first()->password))->toBeTrue();
    });

    it('rejects each kind of bad input', function (array $change, string $field) use ($valid): void {
        $this->post(route('register.store'), $change + $valid)->assertSessionHasErrors($field);

        expect(User::query()->count())->toBe(0);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'no email' => [['email' => ''], 'email'],
        'invalid email' => [['email' => 'nope'], 'email'],
        'password too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
        'confirmation does not match' => [['password_confirmation' => 'different'], 'password'],
    ]);

    it('rejects an email that is already registered', function () use ($valid): void {
        Portal::user(['email' => 'new@example.com']);

        $this->post(route('register.store'), $valid)->assertSessionHasErrors('email');

        expect(User::query()->count())->toBe(1);
    });
});

describe('forgotten password', function (): void {
    it('sends a reset link to a known email', function (): void {
        Notification::fake();
        $user = Portal::user();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('sends nothing to an unknown email', function (): void {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        Notification::assertNothingSent();
    });

    it('lets the user choose a new password with a valid link', function (): void {
        $user = Portal::user();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'Br4nd-New-Passw0rd!', 'password_confirmation' => 'Br4nd-New-Passw0rd!',
        ])->assertSessionHasNoErrors();

        expect(Hash::check('Br4nd-New-Passw0rd!', $user->fresh()->password))->toBeTrue();
    });

    it('refuses a link with a wrong token', function (): void {
        $user = Portal::user();

        $this->post(route('password.update'), [
            'token' => 'wrong-token', 'email' => $user->email,
            'password' => 'Br4nd-New-Passw0rd!', 'password_confirmation' => 'Br4nd-New-Passw0rd!',
        ])->assertSessionHasErrors();

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });
});
