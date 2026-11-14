<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    /** Whether the dialog is on screen. */
    public bool $open = false;

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();

        $this->open = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'open',
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();
    }
}; ?>

<div>
@if ($open)
    <div class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-night/60 p-5 backdrop-blur-sm" x-data x-on:keydown.escape.window="$wire.closeModal()" role="dialog" aria-modal="true" aria-labelledby="tf-title">
        <div x-trap.noscroll class="my-auto w-full max-w-md rounded-3xl bg-white p-7 shadow-2xl sm:p-8">

            <div class="text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-night text-mint">
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM13.5 14.25h.75v.75h-.75v-.75zM18.75 14.25h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM18.75 19.5h.75v.75h-.75v-.75zM16.125 16.875h.75v.75h-.75v-.75z"/></svg>
                </span>
                <h3 id="tf-title" class="mt-4 font-display text-2xl font-extrabold tracking-tight">
                    {{ $showVerificationStep ? __('settings.security.two_factor.verify_title') : __('settings.security.two_factor.modal_title') }}
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-night/65">
                    {{ $showVerificationStep ? __('settings.security.two_factor.verify_text') : __('settings.security.two_factor.setup_text') }}
                </p>
            </div>

            @if ($showVerificationStep)
                {{-- Step 2: confirm with a code --}}
                <form wire:submit="confirmTwoFactor" class="mt-6 space-y-5">
                    <x-portal.input wire:model="code" name="code" :label="__('settings.security.two_factor.code')" inputmode="numeric" maxlength="6" pattern="[0-9]*" autocomplete="one-time-code" placeholder="000000" autofocus
                        class="text-center font-display text-2xl tracking-[0.5em]" />

                    <div class="flex gap-3">
                        <button type="button" wire:click="resetVerification" class="flex-1 rounded-xl px-5 py-3 text-sm font-bold text-night ring-1 ring-lav-deep hover:ring-brand focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('settings.security.two_factor.back') }}</button>
                        <button type="submit" x-bind:disabled="$wire.code.length < 6" wire:loading.attr="disabled" wire:target="confirmTwoFactor"
                            class="flex-1 rounded-xl bg-night px-5 py-3 text-sm font-bold text-white hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">{{ __('settings.security.two_factor.confirm') }}</button>
                    </div>
                </form>
            @else
                {{-- Step 1: scan the QR code --}}
                @error('setupData')
                    <p class="mt-5 rounded-xl bg-coral/10 px-4 py-3 text-sm font-semibold text-coral-deep ring-1 ring-coral/30" role="alert">{{ __('settings.security.two_factor.setup_failed') }}</p>
                @enderror

                <div class="mx-auto mt-6 aspect-square w-56 rounded-2xl bg-white p-3 ring-1 ring-lav-deep" role="img" aria-label="{{ __('settings.security.two_factor.qr_label') }}" dir="ltr">
                    @if ($qrCodeSvg !== '')
                        <div class="[&_svg]:h-auto [&_svg]:w-full">{!! $qrCodeSvg !!}</div>
                    @else
                        <div class="h-full w-full animate-pulse rounded-xl bg-lav"></div>
                    @endif
                </div>

                <button type="button" wire:click="showVerificationIfNecessary" @disabled($errors->has('setupData'))
                    class="mt-6 w-full rounded-xl bg-linear-to-r from-brand to-brand-deep px-6 py-3.5 font-bold text-white shadow-[0_10px_30px_-10px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:opacity-50">{{ __('settings.security.two_factor.continue') }}</button>

                @if ($manualSetupKey !== '')
                    <div class="mt-6 flex items-center gap-3 text-xs font-semibold text-night/45" aria-hidden="true">
                        <span class="h-px flex-1 bg-lav-deep"></span>{{ __('settings.security.two_factor.or_manual') }}<span class="h-px flex-1 bg-lav-deep"></span>
                    </div>

                    <div class="mt-4" dir="ltr"
                         x-data="{ copied: false, async copy() { try { await navigator.clipboard.writeText(@js($manualSetupKey)); this.copied = true; setTimeout(() => this.copied = false, 1500) } catch (e) {} } }">
                        <div class="flex items-stretch overflow-hidden rounded-xl ring-1 ring-lav-deep">
                            <input type="text" readonly value="{{ $manualSetupKey }}" aria-label="{{ __('settings.security.two_factor.key') }}" class="w-full min-w-0 bg-transparent px-4 py-3 font-mono text-sm font-bold outline-none">
                            <button type="button" x-on:click="copy()" class="shrink-0 border-s border-lav-deep px-4 text-sm font-bold text-brand-deep hover:bg-lav focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">
                                <span x-show="!copied">{{ __('settings.security.two_factor.copy') }}</span>
                                <span x-show="copied" x-cloak class="text-mint-deep">{{ __('settings.security.two_factor.copied') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
            @endif

            <button type="button" wire:click="closeModal" class="mx-auto mt-6 block text-sm font-bold text-night/55 underline-offset-4 hover:text-night hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('settings.security.two_factor.close') }}</button>
        </div>
    </div>
@endif
</div>
