<?php

use App\Actions\Account\DeleteAccount;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user(['name' => 'Layla Hassan', 'email' => 'layla@example.com']);   // password is "password"
});

describe('sad path', function (): void {
    it('needs the correct password', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.delete-user-form')
            ->set('password', 'wrong-password')->call('deleteUser')->assertHasErrors(['password']);

        expect(User::query()->find($this->user->id))->not->toBeNull();
    });

    it('needs a password at all', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.delete-user-form')->call('deleteUser')->assertHasErrors(['password']);

        expect(User::query()->find($this->user->id))->not->toBeNull();
    });
});

describe('an account with nothing attached', function (): void {
    it('is deleted completely and the user is logged out', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.delete-user-form')
            ->set('password', 'password')->call('deleteUser')->assertHasNoErrors()->assertRedirect('/');

        expect(User::query()->find($this->user->id))->toBeNull();
        $this->assertGuest();
    });
});

describe('an account that has payments and exams', function (): void {
    beforeEach(function (): void {
        $type = Portal::examType();
        $this->subscription = Portal::subscribe($this->user);
        $this->payment = Portal::payment($this->subscription, 'paid');
        $this->attempt = Portal::rawAttempt($this->user, $type, ['completed_at' => now(), 'score' => 70]);
        $this->user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();
    });

    it('is anonymised instead of deleted, so accounting records survive', function (): void {
        Livewire::actingAs($this->user)->test('pages::settings.delete-user-form')
            ->set('password', 'password')->call('deleteUser')->assertRedirect('/');

        $user = User::query()->findOrFail($this->user->id);

        expect($user->name)->toBe('Deleted user')
            ->and($user->email)->toBe("deleted-{$user->id}@deleted.invalid")
            ->and($user->email_verified_at)->toBeNull()
            ->and($user->two_factor_secret)->toBeNull()
            ->and($user->two_factor_recovery_codes)->toBeNull()
            ->and($user->two_factor_confirmed_at)->toBeNull()
            ->and(Subscription::query()->count())->toBe(1)
            ->and(Payment::query()->count())->toBe(1)
            ->and(ExamAttempt::query()->count())->toBe(1);
        $this->assertGuest();
    });

    it('can no longer be signed in to with the old details', function (): void {
        resolve(DeleteAccount::class)->handle($this->user);

        expect(Auth::attempt(['email' => 'layla@example.com', 'password' => 'password']))->toBeFalse()
            ->and(Hash::check('password', User::query()->find($this->user->id)->password))->toBeFalse();
    });

    it('frees the email address for a new sign-up', function (): void {
        resolve(DeleteAccount::class)->handle($this->user);

        expect(User::query()->where('email', 'layla@example.com')->exists())->toBeFalse();
    });
});

it('keeps other users untouched', function (): void {
    $other = Portal::user(['name' => 'Someone Else']);

    resolve(DeleteAccount::class)->handle($this->user);

    expect(User::query()->find($other->id)->name)->toBe('Someone Else');
});
