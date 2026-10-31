<x-layouts::auth :title="__('auth_ui.reset.title')" :heading="__('auth_ui.reset.heading')" :lead="__('auth_ui.reset.lead')">
    <x-portal.status :status="session('status')" />

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <x-portal.input name="email" type="email" :label="__('auth_ui.common.email')" :value="request('email')" required autocomplete="email" />
        <x-portal.input name="password" type="password" :label="__('auth_ui.common.password')" required autofocus autocomplete="new-password" />
        <x-portal.input name="password_confirmation" type="password" :label="__('auth_ui.common.confirm')" required autocomplete="new-password" />

        <x-portal.button>{{ __('auth_ui.reset.submit') }}</x-portal.button>
    </form>
</x-layouts::auth>
