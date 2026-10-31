@props(['list'])

<section id="faq" class="scroll-mt-4 bg-lav py-24" x-data="{ open: 0 }">
    <div class="mx-auto max-w-3xl px-5">
        <h2 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">{{ __('landing.faq.title') }}
        </h2>
        <div class="mt-10 space-y-3">
            @foreach ($list('landing.faq.items') as $i => $item)
                <div class="rounded-2xl bg-white transition"
                    :class="open === {{ $i }} && 'shadow-lg shadow-brand/10 ring-2 ring-brand/30'">
                    <h3>
                        <button type="button"
                            @click="open = open === {{ $i }} ? null : {{ $i }}"
                            :aria-expanded="open === {{ $i }}"
                            class="flex w-full items-center justify-between gap-4 rounded-2xl px-6 py-5 text-start font-display text-lg font-bold focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">
                            <span>{{ $item['q'] ?? '' }}</span>
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-lav text-xl leading-none text-brand-deep transition"
                                :class="open === {{ $i }} && 'rotate-45 bg-brand text-white'"
                                aria-hidden="true">+</span>
                        </button>
                    </h3>
                    <p x-show="open === {{ $i }}" x-collapse x-cloak
                        class="px-6 pb-6 leading-relaxed text-night/70">{{ $item['a'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
