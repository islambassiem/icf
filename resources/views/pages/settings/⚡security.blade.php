<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            // A setup that was started but never confirmed is thrown away.
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<div>
<x-settings.shell :heading="__('settings.security.heading')" :lead="__('settings.security.lead')">

    {{-- ===== Password ===== --}}
    <x-settings.card :title="__('settings.security.password.title')" :text="__('settings.security.password.text')">
        <form method="POST" wire:submit="updatePassword" class="space-y-5">
            <x-portal.input wire:model="current_password" name="current_password" type="password" :label="__('settings.security.password.current')" required autocomplete="current-password" />
            <x-portal.input wire:model="password" name="password" type="password" :label="__('settings.security.password.new')" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />
            <x-portal.input wire:model="password_confirmation" name="password_confirmation" type="password" :label="__('settings.security.password.confirm')" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />

            <div class="flex items-center gap-4">
                <x-portal.button :full="false" class="px-8" wire:loading.attr="disabled" wire:target="updatePassword" data-test="update-password-button">{{ __('settings.security.password.save') }}</x-portal.button>
                <x-settings.saved on="password-updated" />
            </div>
        </form>
    </x-settings.card>

    {{-- ===== Two-factor authentication ===== --}}
    @if ($canManageTwoFactor)
        <x-settings.card :title="__('settings.security.two_factor.title')" :text="__('settings.security.two_factor.text')" wire:cloak>
            <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-lav px-3.5 py-1.5 text-sm font-bold">
                <span class="h-2.5 w-2.5 rounded-full {{ $twoFactorEnabled ? 'bg-mint' : 'bg-night/25' }}"></span>
                {{ $twoFactorEnabled ? __('settings.security.two_factor.on') : __('settings.security.two_factor.off') }}
            </p>

            @if ($twoFactorEnabled)
                <div class="space-y-5">
                    <p class="text-sm leading-relaxed text-night/65">{{ __('settings.security.two_factor.active_text') }}</p>

                    <button type="button" wire:click="disable" wire:loading.attr="disabled" wire:target="disable"
                        class="rounded-xl bg-coral/10 px-5 py-2.5 text-sm font-bold text-coral-deep ring-1 ring-coral/30 transition hover:bg-coral/20 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral">{{ __('settings.security.two_factor.disable') }}</button>

                    <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                </div>
            @else
                <button type="button" wire:click="$dispatch('start-two-factor-setup')"
                    class="rounded-xl bg-night px-5 py-2.5 text-sm font-bold text-white transition hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('settings.security.two_factor.enable') }}</button>

                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
            @endif
        </x-settings.card>
    @endif

    {{-- ===== Passkeys ===== --}}
    @if ($canManagePasskeys)
        <x-settings.card :title="__('settings.security.passkeys.title')" :text="__('settings.security.passkeys.text')" wire:cloak>
            @if (count($passkeys) > 0)
                <ul class="divide-y divide-lav-deep rounded-2xl ring-1 ring-lav-deep">
                    @foreach ($passkeys as $passkey)
                        <li wire:key="passkey-{{ $passkey['id'] }}" class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-lav text-brand-deep">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 font-bold">
                                        <span class="truncate">{{ $passkey['name'] ?: __('settings.security.passkeys.unnamed') }}</span>
                                        @if ($passkey['authenticator'])
                                            <span class="rounded-full bg-lav px-2 py-0.5 text-[11px] font-bold text-night/70">{{ $passkey['authenticator'] }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-night/55">
                                        {{ __('settings.security.passkeys.added', ['date' => $passkey['created_at_diff']]) }}
                                        &middot;
                                        {{ $passkey['last_used_at_diff'] ? __('settings.security.passkeys.last_used', ['date' => $passkey['last_used_at_diff']]) : __('settings.security.passkeys.never') }}
                                    </p>
                                </div>
                            </div>
                            <button type="button" wire:click="confirmDelete({{ $passkey['id'] }})"
                                class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-bold text-coral-deep transition hover:bg-coral/10 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral">{{ __('settings.security.passkeys.remove') }}</button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="rounded-2xl bg-lav p-5 text-sm text-night/60">{{ __('settings.security.passkeys.empty') }}</p>
            @endif

            <div class="mt-5">
                <x-passkey-registration />
            </div>
        </x-settings.card>
    @endif

    {{-- Remove-passkey dialog --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 grid place-items-center bg-night/60 p-5 backdrop-blur-sm" x-data x-on:keydown.escape.window="$wire.closeDeleteModal()" role="dialog" aria-modal="true" aria-labelledby="pk-title">
            <div x-trap.noscroll class="w-full max-w-md rounded-3xl bg-white p-7 shadow-2xl">
                <h3 id="pk-title" class="font-display text-xl font-extrabold">{{ __('settings.security.passkeys.confirm_title') }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-night/65">{{ __('settings.security.passkeys.confirm_text', ['name' => $deletingPasskeyName]) }}</p>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" wire:click="closeDeleteModal" class="rounded-xl px-5 py-2.5 text-sm font-bold text-night ring-1 ring-lav-deep hover:ring-brand focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('settings.cancel') }}</button>
                    <button type="button" wire:click="deletePasskey" wire:loading.attr="disabled" wire:target="deletePasskey"
                        class="rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white hover:bg-coral-deep focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral focus-visible:ring-offset-2 disabled:opacity-60">{{ __('settings.security.passkeys.remove') }}</button>
                </div>
            </div>
        </div>
    @endif
</x-settings.shell>
</div>
