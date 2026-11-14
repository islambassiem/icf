<?php

use App\Actions\Account\DeleteAccount;
use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

// The button, the confirmation dialog and the delete action in one component
// (this replaces both delete-user-form and delete-user-modal from the starter kit).
new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete (or anonymise) the currently authenticated user.
     */
    public function deleteUser(Logout $logout, DeleteAccount $deleteAccount): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        $logout();
        $deleteAccount->handle($user);

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-settings.card :title="__('settings.profile.danger_title')" :text="__('settings.profile.danger_text')" danger
    x-data="{ open: {{ $errors->isNotEmpty() ? 'true' : 'false' }} }">

    <p class="text-sm leading-relaxed text-night/60">{{ __('settings.profile.kept_note') }}</p>

    <button type="button" x-on:click="open = true" data-test="delete-user-button"
        class="mt-5 rounded-xl bg-coral px-6 py-3 text-sm font-bold text-white transition hover:bg-coral-deep focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral focus-visible:ring-offset-2">{{ __('settings.profile.delete') }}</button>

    {{-- Confirmation dialog --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-night/60 p-5 backdrop-blur-sm"
         x-on:keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-labelledby="del-title">
        <div x-show="open" x-transition x-trap.noscroll="open" class="w-full max-w-md rounded-3xl bg-white p-7 shadow-2xl">
            <h3 id="del-title" class="font-display text-xl font-extrabold text-coral-deep">{{ __('settings.profile.confirm_title') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-night/65">{{ __('settings.profile.confirm_text') }}</p>

            <form wire:submit="deleteUser" class="mt-5 space-y-5">
                <x-portal.input wire:model="password" name="password" type="password" :label="__('settings.profile.password')" required autocomplete="current-password" />

                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" x-on:click="open = false" class="rounded-xl px-5 py-2.5 text-sm font-bold text-night ring-1 ring-lav-deep hover:ring-brand focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('settings.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="deleteUser" data-test="confirm-delete-user-button"
                        class="rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white hover:bg-coral-deep focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral focus-visible:ring-offset-2 disabled:opacity-60">{{ __('settings.profile.confirm') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-settings.card>
