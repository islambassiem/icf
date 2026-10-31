<footer class="border-t border-white/10 bg-night text-white/60">
    <div
        class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-8 text-sm sm:flex-row sm:items-center sm:justify-between">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('landing.footer.copy') }}</p>
        <div class="flex flex-wrap items-center gap-6">
            <nav class="flex gap-6">
                <a href="{{ url('/terms') }}" class="hover:text-white">{{ __('landing.footer.terms') }}</a>
                <a href="{{ url('/privacy') }}" class="hover:text-white">{{ __('landing.footer.privacy') }}</a>
                <a href="{{ url('/contact') }}" class="hover:text-white">{{ __('landing.footer.contact') }}</a>
            </nav>
            <x-language-switcher />
        </div>
    </div>
</footer>
