<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<div>
<x-settings.shell :heading="__('settings.profile.heading')" :lead="__('settings.profile.lead')">

    <x-settings.card :title="__('settings.profile.info_title')" :text="__('settings.profile.info_text')">
        <form wire:submit="updateProfileInformation" class="space-y-5">
            <x-portal.input wire:model="name" name="name" :label="__('settings.profile.name')" required autofocus autocomplete="name" />
            <x-portal.input wire:model="email" name="email" type="email" :label="__('settings.profile.email')" required autocomplete="email" />

            @if ($this->hasUnverifiedEmail)
                <div class="rounded-2xl bg-sun/25 px-4 py-3 text-sm ring-1 ring-sun/60" role="status">
                    <p class="font-semibold">{{ __('settings.profile.unverified') }}</p>
                    <button type="button" wire:click.prevent="resendVerificationNotification" class="mt-1 font-bold underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('settings.profile.resend') }}</button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-semibold text-mint-deep">{{ __('settings.profile.link_sent') }}</p>
                    @endif
                </div>
            @endif

            <p class="text-xs text-night/50">{{ __('settings.profile.email_changed') }}</p>

            <div class="flex items-center gap-4">
                <x-portal.button :full="false" class="px-8" wire:loading.attr="disabled" wire:target="updateProfileInformation" data-test="update-profile-button">{{ __('settings.profile.save') }}</x-portal.button>
                <x-settings.saved on="profile-updated" />
            </div>
        </form>
    </x-settings.card>

    @if ($this->showDeleteUser)
        <livewire:pages::settings.delete-user-form />
    @endif
</x-settings.shell>
</div>
