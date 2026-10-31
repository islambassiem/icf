@props(['list'])


<section id="report" class="scroll-mt-4 bg-lav py-24">
    <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 lg:grid-cols-2">
        <div>
            <h2 class="font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                {{ __('landing.report.title') }}</h2>
            <p class="mt-5 text-lg leading-relaxed text-night/65">{{ __('landing.report.lead') }}</p>
            <ul class="mt-8 space-y-4 font-medium">
                @foreach ($list('landing.report.bullets') as $item)
                    <li class="flex items-center gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-mint text-night"><svg
                                viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 13l4 4L19 7" />
                            </svg></span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>

        <figure class="rounded-4xl bg-night p-7 text-white shadow-2xl shadow-brand/20 sm:p-9">
            <figcaption class="flex items-center justify-between font-display text-lg font-bold">
                <span>{{ __('landing.report.card_title') }}</span>
                <span
                    class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/70">{{ __('landing.report.example') }}</span>
            </figcaption>
            <div class="mt-8 grid grid-cols-3 gap-4 text-center">
                @foreach ([['competency', 78, '#34F5B5'], ['skill', 64, '#7C5CFF'], ['value', 41, '#FF5C85']] as [$key, $pct, $color])
                    @php
                        $c = 2 * pi() * 40;
                        $label = __('landing.report.' . $key);
                    @endphp
                    <div>
                        <div class="relative mx-auto aspect-square w-full max-w-30" role="img"
                            aria-label="{{ $label }}: {{ $pct }}%">
                            <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                                <circle cx="50" cy="50" r="40" fill="none"
                                    stroke="rgba(255,255,255,.1)" stroke-width="10" />
                                <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $color }}"
                                    stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ round($c, 1) }}"
                                    stroke-dashoffset="{{ round($c * (1 - $pct / 100), 1) }}" />
                            </svg>
                            <span class="absolute inset-0 grid place-items-center font-display text-xl font-extrabold"
                                dir="ltr">{{ $pct }}%</span>
                        </div>
                        <p class="mt-3 text-sm font-medium text-white/70">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-8 rounded-2xl bg-coral/15 p-4 text-sm leading-relaxed ring-1 ring-coral/40">
                <strong class="text-coral">{{ __('landing.report.focus_title') }}</strong>
                {{ __('landing.report.focus_text') }}
            </div>
        </figure>
    </div>
</section>
