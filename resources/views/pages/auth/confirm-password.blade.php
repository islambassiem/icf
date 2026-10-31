<x-layouts::auth :title="__('auth_ui.confirm.title')" :heading="__('auth_ui.confirm.heading')" :lead="__('auth_ui.confirm.lead')">
    <x-portal.status :status="session('status')" />

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf

        <x-portal.input name="password" type="password" :label="__('auth_ui.common.password')" required autofocus autocomplete="current-password" />

        <x-portal.button>{{ __('auth_ui.confirm.submit') }}</x-portal.button>
    </form>
</x-layouts::auth>
