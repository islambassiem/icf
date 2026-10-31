<x-layouts::auth :title="__('auth_ui.two_factor.title')" :heading="__('auth_ui.two_factor.title')">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <p class="-mt-5 mb-7 leading-relaxed text-night/65">
            <span x-show="!recovery">{{ __('auth_ui.two_factor.lead_code') }}</span>
            <span x-show="recovery" x-cloak>{{ __('auth_ui.two_factor.lead_recovery') }}</span>
        </p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
            @csrf

            <div x-show="!recovery">
                <x-portal.input name="code" :label="__('auth_ui.two_factor.code')" inputmode="numeric" maxlength="6" pattern="[0-9]*" autocomplete="one-time-code" placeholder="000000" autofocus
                    class="text-center font-display text-2xl tracking-[0.5em]" x-bind:disabled="recovery" />
            </div>

            <div x-show="recovery" x-cloak>
                <x-portal.input name="recovery_code" :label="__('auth_ui.two_factor.recovery')" autocomplete="one-time-code" x-bind:disabled="!recovery" />
            </div>

            <x-portal.button>{{ __('auth_ui.two_factor.submit') }}</x-portal.button>

            <p class="text-center text-sm">
                <button type="button" x-show="!recovery" x-on:click="recovery = true" class="font-bold text-brand-deep underline-offset-4 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('auth_ui.two_factor.use_recovery') }}</button>
                <button type="button" x-show="recovery" x-cloak x-on:click="recovery = false" class="font-bold text-brand-deep underline-offset-4 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('auth_ui.two_factor.use_code') }}</button>
            </p>
        </form>
    </div>
</x-layouts::auth>
