<x-layouts::auth :title="__('auth_ui.register.title')" :heading="__('auth_ui.register.heading')" :lead="__('auth_ui.register.lead')">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf

        <x-portal.input name="name" :label="__('auth_ui.common.name')" required autofocus autocomplete="name" />
        <x-portal.input name="email" type="email" :label="__('auth_ui.common.email')" placeholder="{{ __('auth_ui.common.email_ph') }}" required autocomplete="email" />
        <x-portal.input name="password" type="password" :label="__('auth_ui.common.password')" required autocomplete="new-password" />
        <x-portal.input name="password_confirmation" type="password" :label="__('auth_ui.common.confirm')" required autocomplete="new-password" />

        <x-portal.button>{{ __('auth_ui.register.submit') }}</x-portal.button>
    </form>

    <p class="mt-7 text-center text-sm text-night/65">
        {{ __('auth_ui.register.have_account') }}
        <x-portal.link href="{{ route('login') }}" wire:navigate>{{ __('auth_ui.register.login') }}</x-portal.link>
    </p>
</x-layouts::auth>
