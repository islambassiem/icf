<section class="overflow-hidden border-y border-white/10 bg-night-2 py-5 text-white" aria-label="Highlights"
    dir="ltr">
    <div class="flex w-max animate-marquee gap-12">
        @foreach ([1, 2] as $copy)
            <ul class="flex shrink-0 items-center gap-12 font-display text-lg font-bold"
                @if ($copy === 2) aria-hidden="true" @endif>
                @foreach (__('landing.ticker') as $t)
                    <li class="flex items-center gap-12 whitespace-nowrap">{{ $t }} <span
                            class="text-brand-soft">&#10022;</span></li>
                @endforeach
            </ul>
        @endforeach
    </div>
</section>
