<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div class="space-y-4 rounded-2xl bg-lav/70 p-5 sm:p-6" wire:cloak x-data="{ showRecoveryCodes: false }">
    <div>
        <h3 class="flex items-center gap-2 font-display text-lg font-bold">
            <svg viewBox="0 0 24 24" class="h-5 w-5 text-brand-deep" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            {{ __('settings.security.two_factor.recovery_title') }}
        </h3>
        <p class="mt-1 text-sm leading-relaxed text-night/65">{{ __('settings.security.two_factor.recovery_text') }}</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="button" x-show="!showRecoveryCodes" x-on:click="showRecoveryCodes = true" aria-expanded="false" aria-controls="recovery-codes-section"
            class="rounded-xl bg-night px-5 py-2.5 text-sm font-bold text-white transition hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('settings.security.two_factor.show') }}</button>

        <button type="button" x-show="showRecoveryCodes" x-cloak x-on:click="showRecoveryCodes = false" aria-expanded="true" aria-controls="recovery-codes-section"
            class="rounded-xl bg-night px-5 py-2.5 text-sm font-bold text-white transition hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('settings.security.two_factor.hide') }}</button>

        @if (filled($recoveryCodes))
            <button type="button" x-show="showRecoveryCodes" x-cloak wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes"
                class="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-night ring-1 ring-lav-deep transition hover:ring-brand focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-60">{{ __('settings.security.two_factor.regenerate') }}</button>
        @endif
    </div>

    <div x-show="showRecoveryCodes" x-cloak x-transition id="recovery-codes-section" x-bind:aria-hidden="!showRecoveryCodes">
        @error('recoveryCodes')
            <p class="rounded-xl bg-coral/10 px-4 py-3 text-sm font-semibold text-coral-deep ring-1 ring-coral/30" role="alert">{{ $message }}</p>
        @enderror

        @if (filled($recoveryCodes))
            <ul class="grid gap-2 sm:grid-cols-2" dir="ltr" aria-label="{{ __('settings.security.two_factor.recovery_title') }}">
                @foreach ($recoveryCodes as $code)
                    <li class="select-text rounded-lg bg-white px-3.5 py-2 text-center font-mono text-sm font-bold ring-1 ring-lav-deep" wire:loading.class="opacity-50 animate-pulse">{{ $code }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
