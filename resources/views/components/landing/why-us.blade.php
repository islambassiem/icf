@props(['list'])

@php
    $icons = [
        'price' =>
            'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'idea' =>
            'M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
        'chart' =>
            'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        'grid' =>
            'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
        'again' =>
            'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
    ];
@endphp


<section id="why-us" class="relative scroll-mt-4 overflow-hidden bg-night py-24 text-white">
    <div class="pointer-events-none absolute left-1/2 top-0 h-96 w-3xl -translate-x-1/2 rounded-full bg-brand/30 blur-[120px]"
        aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-5">
        <h2 class="max-w-3xl font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
            {{ __('landing.why_us.title') }} {{ config('app.name') }}</h2>
        <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/65">{{ __('landing.why_us.lead') }}</p>

        <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($list('landing.why_us.items') as $item)
                <article class="glass group rounded-3xl p-7 transition hover:bg-white/10">
                    <span
                        class="grid h-12 w-12 place-items-center rounded-2xl bg-linear-to-br from-brand to-mint text-night">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $icons[$item['icon'] ?? ''] ?? $icons['idea'] }}" />
                        </svg>
                    </span>
                    <h3 class="mt-5 font-display text-xl font-bold">{{ $item['title'] ?? '' }}</h3>
                    <p class="mt-2 leading-relaxed text-white/65">{{ $item['text'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
