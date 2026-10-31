<x-layouts::auth :title="__('auth_ui.forgot.title')" :heading="__('auth_ui.forgot.heading')" :lead="__('auth_ui.forgot.lead')">
    <x-portal.status :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-portal.input name="email" type="email" :label="__('auth_ui.common.email')" placeholder="{{ __('auth_ui.common.email_ph') }}" required autofocus autocomplete="email" />

        <x-portal.button>{{ __('auth_ui.forgot.submit') }}</x-portal.button>
    </form>

    <p class="mt-7 text-center text-sm">
        <x-portal.link href="{{ route('login') }}">{{ __('auth_ui.forgot.back') }}</x-portal.link>
    </p>
</x-layouts::auth>
