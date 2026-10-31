@props(['level', 'sample', 'list'])

@php
    $quizData = ['best' => $sample['best'], 'worst' => $sample['worst']];
    $icons = [
        'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    $labels = [
        'option' => __('landing.card.option'),
        'both' => __('landing.card.both'),
        'one' => __('landing.card.one'),
        'none' => __('landing.card.none'),
    ];
@endphp

<section class="relative isolate bg-night text-white">
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="bg-grid absolute inset-0"></div>
        <div class="absolute -inset-s-32 top-10 h-112 w-md animate-blob rounded-full bg-brand/50 blur-[110px]">
        </div>
        <div
            class="absolute -inset-e-24 top-64 h-96 w-[24rem] animate-blob rounded-full bg-mint/25 blur-[110px] [animation-delay:-6s]">
        </div>
        <div
            class="absolute bottom-0 inset-s-1/3 h-72 w-72 animate-blob rounded-full bg-coral/25 blur-[110px] [animation-delay:-11s]">
        </div>
    </div>

    <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 pb-24 pt-36 lg:grid-cols-[1.05fr_1fr] lg:pt-44">
        <div>
            <p class="glass inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium text-white/90">
                <span class="h-2 w-2 rounded-full bg-mint shadow-[0_0_10px_#34F5B5]"></span>
                {{ __('landing.hero.badge') }}
            </p>
            <h1
                class="mt-6 font-display text-[2.6rem] font-extrabold leading-[1.04] tracking-tight sm:text-6xl xl:text-7xl">
                {{ __('landing.hero.title_1') }}
                <span class="text-gradient block">{{ __('landing.hero.title_2') }}</span>
            </h1>
            <p class="mt-7 max-w-xl text-lg leading-relaxed text-white/70">{{ __('landing.hero.lead') }}</p>
            <div class="mt-9 flex flex-wrap items-center gap-4">
                <a href="{{ url('/register') }}"
                    class="group inline-flex items-center gap-2 rounded-full bg-linear-to-r from-brand to-brand-deep px-8 py-4 font-bold text-white shadow-[0_10px_40px_-8px_#7C5CFF] transition hover:shadow-[0_14px_50px_-6px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-night">
                    {{ __('landing.hero.cta') }}
                    <svg viewBox="0 0 24 24"
                        class="h-5 w-5 transition group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </a>
                <a href="#sample"
                    class="glass rounded-full px-7 py-4 font-semibold text-white hover:bg-white/15 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint">{{ __('landing.hero.sample') }}</a>
            </div>
            <ul class="mt-10 flex flex-wrap gap-x-8 gap-y-3 text-sm text-white/70">
                @foreach ($list('landing.hero.checks') as $check)
                    <li class="flex items-center gap-2"><span class="text-mint">&#10003;</span> {{ $check }}
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Interactive exam card --}}
        <div id="sample" class="relative scroll-mt-24">
            <div class="absolute -inset-4 -z-10 rounded-[2.5rem] bg-linear-to-br from-brand/40 via-transparent to-mint/30 blur-2xl"
                aria-hidden="true"></div>

            <div wire:key="quiz-{{ $level }}"
                class="animate-float rounded-3xl bg-white p-5 text-night shadow-2xl shadow-black/40 sm:p-7"
                x-data="{
                    q: {{ Js::from($quizData) }},
                    L: {{ Js::from($labels) }},
                    best: null,
                    worst: null,
                    drag: null,
                    checked: false,
                    secs: 70 * 60,
                    init() { setInterval(() => { if (this.secs > 0) this.secs-- }, 1000) },
                    get clock() { return String(Math.floor(this.secs / 60)).padStart(2, '0') + ':' + String(this.secs % 60).padStart(2, '0') },
                    assign(zone, i) {
                        if (i === null || this.checked) return;
                        if (zone === 'best') {
                            if (this.worst === i) this.worst = null;
                            this.best = i
                        } else {
                            if (this.best === i) this.best = null;
                            this.worst = i
                        }
                    },
                    reset() {
                        this.best = null;
                        this.worst = null;
                        this.checked = false
                    },
                    get ready() { return this.best !== null && this.worst !== null },
                    get score() { return (this.best === this.q.best ? 1 : 0) + (this.worst === this.q.worst ? 1 : 0) }
                }">

                <div class="flex items-center justify-between gap-3">
                    <div class="inline-flex rounded-full bg-lav p-1 text-sm font-bold" role="tablist"
                        aria-label="{{ __('landing.card.exam_type') }}" dir="ltr">
                        @foreach (['ACC', 'PCC'] as $type)
                            <button type="button" role="tab"
                                aria-selected="{{ $level === $type ? 'true' : 'false' }}"
                                wire:click="setLevel('{{ $type }}')"
                                class="rounded-full px-5 py-1.5 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand {{ $level === $type ? 'bg-night text-white' : 'text-night/60 hover:text-night' }}">{{ $type }}</button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2 rounded-full bg-night px-4 py-1.5 font-display text-sm font-bold tabular-nums text-mint"
                        aria-label="{{ __('landing.card.time_left') }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round">
                            <path d="{{ $icons['clock'] }}" />
                        </svg>
                        <span x-text="clock" dir="ltr">70:00</span>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-1.5" aria-label="{{ __('landing.card.map') }}">
                    @foreach (range(1, 12) as $n)
                        <span
                            class="h-1.5 flex-1 rounded-full {{ $n < 4 ? 'bg-brand' : ($n === 4 ? 'bg-sun' : 'bg-lav-deep') }}"></span>
                    @endforeach
                </div>
                <p class="mt-2 text-xs font-medium text-night/55">
                    {{ __('landing.card.meta', ['area' => $sample['area'], 'profile' => $sample['profile']]) }}</p>

                <p class="mt-4 text-[1.05rem] font-semibold leading-snug">{{ $sample['question'] }}</p>

                <ul class="mt-4 space-y-2">
                    @foreach ($sample['options'] as $i => $option)
                        <li draggable="true"
                            @dragstart="drag = {{ $i }}; $event.dataTransfer.setData('text/plain', '{{ $i }}')"
                            @dragend="drag = null"
                            class="flex cursor-grab items-start gap-3 rounded-xl border-2 px-3.5 py-3 text-[15px] leading-snug transition active:cursor-grabbing"
                            :class="{
                                'border-mint bg-mint/10': checked && {{ $i }} === q.best,
                                'border-coral bg-coral/10': checked && {{ $i }} === q.worst,
                                'border-brand bg-brand/10': !checked && (best === {{ $i }} || worst ===
                                    {{ $i }}),
                                'border-lav-deep bg-white hover:border-brand/50': !(checked && (
                                    {{ $i }} === q.best || {{ $i }} === q.worst)) && !(!
                                    checked && (best === {{ $i }} || worst === {{ $i }})
                                ),
                                'opacity-40': drag === {{ $i }}
                            }">
                            <span class="mt-0.5 select-none text-night/30" aria-hidden="true">&#8942;&#8942;</span>
                            <span class="flex-1">{{ $option }}</span>
                            <span class="flex shrink-0 gap-1" x-show="!checked">
                                <button type="button" @click="assign('best', {{ $i }})"
                                    class="rounded-md px-2 py-0.5 text-xs font-bold text-mint-deep ring-1 ring-mint-deep/30 hover:bg-mint/20 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint-deep"
                                    aria-label="{{ __('landing.card.best_aria', ['n' => $i + 1]) }}">{{ __('landing.card.best_btn') }}</button>
                                <button type="button" @click="assign('worst', {{ $i }})"
                                    class="rounded-md px-2 py-0.5 text-xs font-bold text-coral-deep ring-1 ring-coral-deep/30 hover:bg-coral/15 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral-deep"
                                    aria-label="{{ __('landing.card.worst_aria', ['n' => $i + 1]) }}">{{ __('landing.card.worst_btn') }}</button>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm font-bold">
                    <div @dragover.prevent @drop.prevent="assign('best', drag)"
                        class="grid min-h-14 place-items-center rounded-xl border-2 border-dashed border-mint-deep/50 bg-mint/5 px-2 py-2 text-center text-mint-deep transition"
                        :class="drag !== null && 'bg-mint/20 border-mint-deep'">
                        <span x-show="best === null">{{ __('landing.card.best_zone') }}</span>
                        <span x-show="best !== null" x-cloak x-text="L.option + ' ' + (best + 1)"></span>
                    </div>
                    <div @dragover.prevent @drop.prevent="assign('worst', drag)"
                        class="grid min-h-14 place-items-center rounded-xl border-2 border-dashed border-coral-deep/50 bg-coral/5 px-2 py-2 text-center text-coral-deep transition"
                        :class="drag !== null && 'bg-coral/20 border-coral-deep'">
                        <span x-show="worst === null">{{ __('landing.card.worst_zone') }}</span>
                        <span x-show="worst !== null" x-cloak x-text="L.option + ' ' + (worst + 1)"></span>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="button" x-show="!checked" @click="checked = true" :disabled="!ready"
                        class="w-full rounded-xl bg-night py-3 font-bold text-white transition hover:bg-night-3 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                        <span x-show="!ready">{{ __('landing.card.pick') }}</span>
                        <span x-show="ready" x-cloak>{{ __('landing.card.see') }}</span>
                    </button>
                    <div x-show="checked" x-cloak class="space-y-2 text-sm leading-relaxed">
                        <p class="font-display text-base font-bold"
                            x-text="score === 2 ? L.both : (score === 1 ? L.one : L.none)"></p>
                        <p><strong
                                class="text-mint-deep">{{ __('landing.card.best_fb', ['n' => $sample['best'] + 1]) }}</strong>
                            {{ $sample['best_note'] }}</p>
                        <p><strong
                                class="text-coral-deep">{{ __('landing.card.worst_fb', ['n' => $sample['worst'] + 1]) }}</strong>
                            {{ $sample['worst_note'] }}</p>
                        <button type="button" @click="reset()"
                            class="font-bold text-brand-deep underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('landing.card.retry') }}</button>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-center text-xs text-white/50">{{ __('landing.card.hint') }}</p>
        </div>
    </div>
</section>
