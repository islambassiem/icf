<section class="relative isolate overflow-hidden bg-night py-24 text-center text-white">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="bg-grid absolute inset-0"></div>
        <div
            class="absolute left-1/2 top-1/2 h-96 w-160 -translate-x-1/2 -translate-y-1/2 rounded-full bg-linear-to-r from-brand/60 via-mint/30 to-coral/40 blur-[110px]">
        </div>
    </div>
    <div class="mx-auto max-w-3xl px-5">
        <h2 class="font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">
            {{ __('landing.cta.title') }}</h2>
        <p class="mx-auto mt-5 max-w-xl text-lg text-white/70">{{ __('landing.cta.lead') }}</p>
        <a href="{{ url('/register') }}"
            class="mt-10 inline-block rounded-full bg-white px-10 py-4 text-lg font-bold text-night shadow-[0_10px_40px_-8px_#34F5B5] transition hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-night">{{ __('landing.cta.button') }}</a>
    </div>
</section>
