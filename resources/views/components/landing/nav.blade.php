<header
    class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-night/90 shadow-lg shadow-black/10 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-5 py-5">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-display text-xl font-extrabold text-white">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-linear-to-br from-brand to-mint text-night">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 13l4 4L19 7" />
                </svg>
            </span>
            {{ config('app.name') }}
        </a>
        <nav class="glass hidden items-center gap-1 rounded-full px-2 py-1.5 text-sm font-medium text-white/80 lg:flex">
            <a href="#why-mock"
                class="rounded-full px-4 py-1.5 hover:bg-white/10 hover:text-white">{{ __('landing.nav.why_mock') }}</a>
            <a href="#why-us"
                class="rounded-full px-4 py-1.5 hover:bg-white/10 hover:text-white">{{ __('landing.nav.why_us') }}</a>
            <a href="#report"
                class="rounded-full px-4 py-1.5 hover:bg-white/10 hover:text-white">{{ __('landing.nav.report') }}</a>
            <a href="#faq"
                class="rounded-full px-4 py-1.5 hover:bg-white/10 hover:text-white">{{ __('landing.nav.faq') }}</a>
        </nav>
        <div class="flex items-center gap-2">
            <x-language-switcher />
            <a href="{{ url('/login') }}"
                class="hidden px-3 py-2 text-sm font-medium text-white/80 hover:text-white sm:inline-block">{{ __('landing.nav.login') }}</a>
            <a href="{{ url('/register') }}"
                class="rounded-full bg-white px-5 py-2.5 text-sm font-bold whitespace-nowrap text-night hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-night">{{ __('landing.nav.start') }}</a>
        </div>
    </div>
</header>
