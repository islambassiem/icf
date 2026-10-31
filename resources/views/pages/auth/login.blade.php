<x-layouts::auth :title="__('auth_ui.login.title')" :heading="__('auth_ui.login.heading')" :lead="__('auth_ui.login.lead')">
    <x-portal.status :status="session('status')" />

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <x-portal.input name="email" type="email" :label="__('auth_ui.common.email')" placeholder="{{ __('auth_ui.common.email_ph') }}" required autofocus autocomplete="email" />

        <div>
            <x-portal.input name="password" type="password" :label="__('auth_ui.common.password')" required autocomplete="current-password" />
            @if (Route::has('password.request'))
                <div class="mt-2 text-end text-sm">
                    <x-portal.link href="{{ route('password.request') }}" wire:navigate>{{ __('auth_ui.login.forgot') }}</x-portal.link>
                </div>
            @endif
        </div>

        <label class="flex cursor-pointer items-center gap-3 text-sm font-medium">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-5 w-5 rounded-md border-2 border-lav-deep accent-brand focus:ring-2 focus:ring-brand">
            {{ __('auth_ui.login.remember') }}
        </label>

        <x-portal.button>{{ __('auth_ui.login.submit') }}</x-portal.button>
    </form>

    @if (Route::has('register'))
        <p class="mt-7 text-center text-sm text-night/65">
            {{ __('auth_ui.login.no_account') }}
            <x-portal.link href="{{ route('register') }}" wire:navigate>{{ __('auth_ui.login.register') }}</x-portal.link>
        </p>
    @endif
</x-layouts::auth>
