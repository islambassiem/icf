@props(['list'])

@php
    $mock = $list('landing.mock');
    $m = fn(string $path): string => (string) data_get($mock, $path, '');
@endphp
<section id="why-mock" class="scroll-mt-30 bg-lav pb-24">
    <div class="mx-auto max-w-7xl px-5">
        <h2 class="max-w-3xl font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
            {{ $m('title') }}</h2>
        <p class="mt-5 max-w-2xl text-lg leading-relaxed text-night/65">{{ $m('lead') }}</p>

        <div class="mt-12 grid gap-5 lg:grid-cols-6">
            <article class="relative overflow-hidden rounded-3xl bg-night p-8 text-white lg:col-span-4">
                <h3 class="font-display text-2xl font-bold">{{ $m('format.title') }}</h3>
                <p class="mt-3 max-w-md leading-relaxed text-white/70">{{ $m('format.text') }}</p>
                <div class="mt-8 flex gap-3" aria-hidden="true">
                    <div
                        class="grid h-16 flex-1 place-items-center rounded-2xl border-2 border-dashed border-mint/60 bg-mint/10 text-sm font-bold text-mint">
                        {{ __('landing.card.best_btn') }}</div>
                    <div
                        class="grid h-16 flex-1 place-items-center rounded-2xl border-2 border-dashed border-coral/60 bg-coral/10 text-sm font-bold text-coral">
                        {{ __('landing.card.worst_btn') }}</div>
                </div>
                <div class="absolute -inset-e-10 -top-10 h-40 w-40 rounded-full bg-brand/40 blur-3xl"
                    aria-hidden="true"></div>
            </article>
            <article class="rounded-3xl bg-linear-to-br from-brand to-brand-deep p-8 text-white lg:col-span-2">
                <h3 class="font-display text-2xl font-bold">{{ $m('pace.title') }}</h3>
                <p class="mt-3 leading-relaxed text-white/80">{{ $m('pace.text') }}</p>
                <p class="mt-6 font-display text-5xl font-extrabold tabular-nums" dir="ltr">
                    {{ $m('pace.stat') }}</p>
                <p class="text-sm text-white/70">{{ $m('pace.stat_label') }}</p>
            </article>
            <article class="rounded-3xl bg-white p-8 lg:col-span-2">
                <h3 class="font-display text-xl font-bold">{{ $m('gaps.title') }}</h3>
                <p class="mt-3 leading-relaxed text-night/65">{{ $m('gaps.text') }}</p>
            </article>
            <article class="rounded-3xl bg-white p-8 lg:col-span-2">
                <h3 class="font-display text-xl font-bold">{{ $m('why.title') }}</h3>
                <p class="mt-3 leading-relaxed text-night/65">{{ $m('why.text') }}</p>
            </article>
            <article class="rounded-3xl bg-mint p-8 text-night lg:col-span-2">
                <h3 class="font-display text-xl font-bold">{{ $m('focus.title') }}</h3>
                <p class="mt-3 leading-relaxed text-night/75">{{ $m('focus.text') }}</p>
            </article>
            <article class="flex flex-col justify-between gap-6 rounded-3xl bg-night-2 p-8 text-white lg:col-span-3">
                <h3 class="font-display text-2xl font-bold">{{ $m('calm.title') }}</h3>
                <p class="leading-relaxed text-white/70">{{ $m('calm.text') }}</p>
            </article>
            <article class="flex flex-col justify-between gap-6 rounded-3xl bg-coral p-8 text-night lg:col-span-3">
                <h3 class="font-display text-2xl font-bold">{{ $m('plan.title') }}</h3>
                <p class="leading-relaxed text-night/80">{{ $m('plan.text') }}</p>
            </article>
        </div>
    </div>
</section>
