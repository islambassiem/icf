<x-layouts::auth :title="__('auth_ui.verify.title')" :heading="__('auth_ui.verify.heading')" :lead="__('auth_ui.verify.lead')">
    @if (session('status') === 'verification-link-sent')
        <x-portal.status :status="__('auth_ui.verify.sent')" />
    @endif

    <div class="space-y-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-portal.button>{{ __('auth_ui.verify.resend') }}</x-portal.button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center text-sm">
            @csrf
            <button type="submit" class="font-bold text-brand-deep underline-offset-4 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('auth_ui.verify.logout') }}</button>
        </form>
    </div>
</x-layouts::auth>
