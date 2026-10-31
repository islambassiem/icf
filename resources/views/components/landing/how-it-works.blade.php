@props(['list'])

<section class="bg-white py-24">
    <div class="mx-auto max-w-7xl px-5">
        <h2 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">
            {{ __('landing.steps.title') }}</h2>
        <ol class="relative mt-14 grid gap-10 md:grid-cols-4">
            <div class="absolute inset-s-6 top-6 hidden h-0.5 w-[calc(100%-3rem)] bg-linear-to-r from-brand via-mint to-coral rtl:bg-linear-to-l md:block"
                aria-hidden="true"></div>
            @foreach ($list('landing.steps.items') as $i => $step)
                <li class="relative">
                    <span
                        class="relative grid h-12 w-12 place-items-center rounded-full bg-night font-display text-lg font-extrabold text-white ring-4 ring-white">{{ $i + 1 }}</span>
                    <h3 class="mt-5 font-display text-xl font-bold">{{ $step['title'] ?? '' }}</h3>
                    <p class="mt-2 leading-relaxed text-night/65">{{ $step['text'] ?? '' }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
