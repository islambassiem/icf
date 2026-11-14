<?php

use App\Models\ExamAttempt;
use App\Models\ExamResponse;
use App\Services\Exams\ExamClock;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::exam')] class extends Component {
    public ExamAttempt $attempt;

    /** Position (1-40) of the question on screen, inside the current part. */
    public int $current = 1;

    public ?string $notice = null;

    public function mount(ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id(), 404);

        $this->attempt = app(ExamClock::class)->sync($attempt);

        // Resume at the first question that still has no answer.
        $this->current = $this->questions->first(fn ($q) => ! $q->response)?->position ?? 1;
    }

    /** part1 | break | part2 | done */
    #[Computed]
    public function phase(): string
    {
        return app(ExamClock::class)->phase($this->attempt);
    }

    #[Computed]
    public function partNumber(): int
    {
        return $this->phase === 'part2' ? 2 : 1;
    }

    #[Computed]
    public function deadline()
    {
        return app(ExamClock::class)->deadline($this->attempt);
    }

    /** The questions of the current part, with the user's answers. */
    #[Computed]
    public function questions(): Collection
    {
        if (! in_array($this->phase, ['part1', 'part2'], true)) {
            return collect();
        }

        return $this->attempt->attemptQuestions()
            ->where('part_number', $this->partNumber)
            ->orderBy('position')
            ->with('response')
            ->get();
    }

    #[Computed]
    public function totals(): array
    {
        $questions = $this->attempt->attemptQuestions();

        return [
            'total' => (clone $questions)->count(),
            'answered' => (clone $questions)->has('response')->count(),
            'part1_total' => (clone $questions)->where('part_number', 1)->count(),
            'part1_answered' => (clone $questions)->where('part_number', 1)->has('response')->count(),
        ];
    }

    /** Move the clock forward if a deadline passed, then forget cached values. */
    private function resync(): void
    {
        $this->attempt = app(ExamClock::class)->sync($this->attempt);

        unset($this->phase, $this->partNumber, $this->deadline, $this->questions, $this->totals);
    }

    /** Called by the timer when it reaches zero, and every 20 seconds as a safety net. */
    public function tick(): void
    {
        $before = $this->phase;
        $this->resync();

        if ($this->phase !== $before) {
            $this->current = 1;
            $this->notice = null;
        }
    }

    public function goTo(int $position): void
    {
        $this->resync();

        $this->current = max(1, min($position, max(1, $this->questions->count())));
        $this->notice = null;
    }

    /** Saves (or replaces) the answer of one question. Rejects anything that is not a valid choice. */
    public function saveAnswer(int $attemptQuestionId, int $bestId, int $worstId): void
    {
        $this->resync();

        // Time ran out, or the part was already submitted: nothing can be changed any more.
        if (! in_array($this->phase, ['part1', 'part2'], true)) {
            return;
        }

        $question = $this->attempt->attemptQuestions()
            ->where('part_number', $this->partNumber)
            ->findOrFail($attemptQuestionId);

        $optionIds = $question->snapshotOptions()->pluck('id')->map(fn ($id) => (int) $id);

        if ($bestId === $worstId || ! $optionIds->contains($bestId) || ! $optionIds->contains($worstId)) {
            return;
        }

        ExamResponse::updateOrCreate(
            ['exam_attempt_question_id' => $question->id],
            ['best_answer_option_id' => $bestId, 'worst_answer_option_id' => $worstId, 'answered_at' => now()],
        );
    }

    /** All questions are mandatory: the part can only be submitted when every one is answered. */
    public function submitPart(): void
    {
        $this->resync();

        if (! in_array($this->phase, ['part1', 'part2'], true)) {
            return;
        }

        $missing = $this->questions->filter(fn ($q) => ! $q->response)->pluck('position')->values();

        if ($missing->isNotEmpty()) {
            $this->current = (int) $missing->first();
            $this->notice = trans_choice('exam.missing', $missing->count(), [
                'count' => $missing->count(),
                'list' => $missing->take(12)->implode(', ') . ($missing->count() > 12 ? '...' : ''),
            ]);

            return;
        }

        $this->notice = null;
        $clock = app(ExamClock::class);

        if ($this->partNumber === 1) {
            $clock->startBreak($this->attempt);
        } else {
            $clock->complete($this->attempt);
        }

        $this->current = 1;
        $this->resync();
    }

    public function startPart2(): void
    {
        $this->resync();

        app(ExamClock::class)->startPart2($this->attempt);

        $this->current = 1;
        $this->resync();
    }
};
?>

<div wire:poll.20s="tick">
@php
    $phase = $this->phase;
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
    $ar = app()->getLocale() === 'ar';
    $text = fn (array $row, string $field) => $ar ? (($row[$field . '_ar'] ?? '') ?: ($row[$field . '_en'] ?? '')) : (($row[$field . '_en'] ?? '') ?: ($row[$field . '_ar'] ?? ''));

    $deadline = $this->deadline;
    $endMs = $deadline ? $deadline->getTimestamp() * 1000 : 0;
    $nowMs = now()->getTimestamp() * 1000;

    // Countdown driven by the SERVER's clock; when it hits zero the server decides what happens next.
    $timerJs = '{ end: __END__, off: __NOW__ - Date.now(), left: 0, fired: false, '
        . 'init() { this.tick(); setInterval(() => this.tick(), 1000) }, '
        . 'tick() { this.left = Math.max(0, Math.round((this.end - (Date.now() + this.off)) / 1000)); if (this.left === 0 && !this.fired) { this.fired = true; $wire.tick() } }, '
        . 'get label() { return String(Math.floor(this.left / 60)).padStart(2, "0") + ":" + String(this.left % 60).padStart(2, "0") } }';
    $timerJs = str_replace(['__END__', '__NOW__'], [$endMs, $nowMs], $timerJs);

    $partTotal = (int) config('portal.exam.parts');
    $perPart = (int) config('portal.exam.questions_per_part');
@endphp

@if (in_array($phase, ['part1', 'part2'], true))
    @php
        $questions = $this->questions;
        $aq = $questions->firstWhere('position', $current) ?? $questions->first();
        $snap = $aq->snapshot();
        $answered = $questions->filter(fn ($q) => $q->response)->count();
        $count = $questions->count();
        $isLast = $current >= $count;
    @endphp

    {{-- ===== Top bar ===== --}}
    <header class="sticky top-0 z-20 border-b border-lav-deep bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-5 py-3">
            <div class="min-w-0">
                <p class="truncate font-display text-lg font-extrabold">{{ $attempt->examType?->name }}</p>
                <p class="text-xs font-medium text-night/55">{{ __('exam.part_label', ['n' => $this->partNumber, 'total' => $partTotal]) }}</p>
            </div>

            <div wire:key="timer-{{ $phase }}-{{ $endMs }}" x-data='{!! $timerJs !!}'
                 class="flex items-center gap-2 rounded-full px-4 py-2 font-display text-lg font-bold tabular-nums transition"
                 :class="left < 300 ? 'bg-coral text-white' : 'bg-night text-mint'" role="timer" aria-label="{{ __('exam.time_left') }}">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="label" dir="ltr">--:--</span>
            </div>

            <a href="{{ $link('dashboard') }}" class="hidden shrink-0 text-sm font-bold text-night/60 underline-offset-4 hover:text-night hover:underline sm:block">{{ __('exam.exit') }}</a>
        </div>
        <div class="h-1 bg-lav-deep"><div class="h-full bg-linear-to-r from-brand to-mint rtl:bg-linear-to-l transition-all" style="width: {{ $count ? round($answered / $count * 100) : 0 }}%"></div></div>
    </header>

    <main class="mx-auto grid max-w-6xl gap-6 px-5 py-8 lg:grid-cols-[1fr_20rem]">
        {{-- ===== Question ===== --}}
        <section aria-labelledby="q-title">
            @if ($notice)
                <p class="mb-5 rounded-xl bg-coral/10 px-4 py-3 text-sm font-semibold text-coral-deep ring-1 ring-coral/30" role="alert">{{ $notice }}</p>
            @endif

            <div wire:key="q-{{ $aq->id }}"
                 class="rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-8"
                 x-data="{
                    best: {{ $aq->response?->best_answer_option_id ?? 'null' }},
                    worst: {{ $aq->response?->worst_answer_option_id ?? 'null' }},
                    drag: null, saving: false, saved: {{ $aq->response ? 'true' : 'false' }},
                    assign(zone, id) {
                        if (id === null) return;
                        if (zone === 'best') { if (this.worst === id) this.worst = null; this.best = id }
                        else { if (this.best === id) this.best = null; this.worst = id }
                        this.save();
                    },
                    save() {
                        if (this.best === null || this.worst === null) { this.saved = false; return }
                        this.saving = true;
                        $wire.saveAnswer({{ $aq->id }}, this.best, this.worst).then(() => { this.saving = false; this.saved = true });
                    },
                    number(id) { return id === null ? '' : this.ids.indexOf(id) + 1 },
                    ids: {{ Js::from(collect($snap['options'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->values()) }}
                 }">

                <p class="text-sm font-bold text-night/50">{{ __('exam.question_of', ['n' => $current, 'total' => $count]) }}</p>
                <h1 id="q-title" class="mt-2 text-xl font-semibold leading-relaxed sm:text-2xl">{{ $text($snap, 'statement') }}</h1>
                <p class="mt-3 text-sm text-night/60">{{ __('exam.instructions') }}</p>

                {{-- Options --}}
                <ul class="mt-6 space-y-3">
                    @foreach ($snap['options'] ?? [] as $i => $option)
                        @php $id = (int) $option['id']; @endphp
                        <li draggable="true"
                            @dragstart="drag = {{ $id }}; $event.dataTransfer.setData('text/plain', '{{ $id }}')" @dragend="drag = null"
                            class="flex cursor-grab items-start gap-3 rounded-2xl border-2 px-4 py-3.5 transition active:cursor-grabbing"
                            :class="{
                                'border-mint bg-mint/10': best === {{ $id }},
                                'border-coral bg-coral/10': worst === {{ $id }},
                                'border-lav-deep bg-white hover:border-brand/40': best !== {{ $id }} && worst !== {{ $id }},
                                'opacity-40': drag === {{ $id }}
                            }">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-lav font-display text-sm font-extrabold">{{ $i + 1 }}</span>
                            <span class="flex-1 leading-relaxed">{{ $text($option, 'statement') }}</span>
                            <span class="flex shrink-0 flex-col gap-1.5 sm:flex-row">
                                <button type="button" @click="assign('best', {{ $id }})" class="rounded-lg px-2.5 py-1 text-xs font-bold ring-1 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint-deep"
                                    :class="best === {{ $id }} ? 'bg-mint text-night ring-mint' : 'text-mint-deep ring-mint-deep/30 hover:bg-mint/20'" :aria-pressed="best === {{ $id }}">{{ __('exam.best_btn') }}</button>
                                <button type="button" @click="assign('worst', {{ $id }})" class="rounded-lg px-2.5 py-1 text-xs font-bold ring-1 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-coral-deep"
                                    :class="worst === {{ $id }} ? 'bg-coral text-white ring-coral' : 'text-coral-deep ring-coral-deep/30 hover:bg-coral/15'" :aria-pressed="worst === {{ $id }}">{{ __('exam.worst_btn') }}</button>
                            </span>
                        </li>
                    @endforeach
                </ul>

                {{-- Drop zones --}}
                <div class="mt-5 grid grid-cols-2 gap-3 text-sm font-bold">
                    <div @dragover.prevent @drop.prevent="assign('best', drag)"
                         class="grid min-h-16 place-items-center rounded-2xl border-2 border-dashed border-mint-deep/50 bg-mint/5 px-3 py-3 text-center text-mint-deep transition" :class="drag !== null && 'border-mint-deep bg-mint/20'">
                        <span x-show="best === null">{{ __('exam.best_zone') }}</span>
                        <span x-show="best !== null" x-cloak x-text="'{{ __('exam.option') }} ' + number(best)"></span>
                    </div>
                    <div @dragover.prevent @drop.prevent="assign('worst', drag)"
                         class="grid min-h-16 place-items-center rounded-2xl border-2 border-dashed border-coral-deep/50 bg-coral/5 px-3 py-3 text-center text-coral-deep transition" :class="drag !== null && 'border-coral-deep bg-coral/20'">
                        <span x-show="worst === null">{{ __('exam.worst_zone') }}</span>
                        <span x-show="worst !== null" x-cloak x-text="'{{ __('exam.option') }} ' + number(worst)"></span>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <button type="button" wire:click="goTo({{ $current - 1 }})" @disabled($current <= 1)
                        class="rounded-xl px-5 py-2.5 text-sm font-bold text-night ring-1 ring-lav-deep transition hover:ring-brand focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-40">{{ __('exam.previous') }}</button>

                    <p class="text-sm font-semibold" aria-live="polite">
                        <span x-show="saving" class="text-night/55">{{ __('exam.saving') }}</span>
                        <span x-show="!saving && saved" x-cloak class="text-mint-deep">&#10003; {{ __('exam.saved') }}</span>
                    </p>

                    <button type="button" wire:click="goTo({{ $current + 1 }})" @disabled($isLast)
                        class="rounded-xl bg-night px-6 py-2.5 text-sm font-bold text-white transition hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">{{ __('exam.next') }}</button>
                </div>
            </div>
        </section>

        {{-- ===== Question map + submit ===== --}}
        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <div class="rounded-3xl bg-white p-6 ring-1 ring-lav-deep">
                <h2 class="font-display text-base font-bold">{{ __('exam.map') }}</h2>
                <p class="mt-1 text-sm text-night/60">{{ __('exam.answered', ['n' => $answered, 'total' => $count]) }}</p>

                <div class="mt-4 grid grid-cols-8 gap-1.5 lg:grid-cols-5">
                    @foreach ($questions as $q)
                        @php $is = $q->position === $current; @endphp
                        <button type="button" wire:click="goTo({{ $q->position }})" aria-label="{{ __('exam.question_of', ['n' => $q->position, 'total' => $count]) }}" @if ($is) aria-current="true" @endif
                            class="grid aspect-square place-items-center rounded-lg text-xs font-bold tabular-nums transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand
                                   {{ $is ? 'bg-sun text-night ring-2 ring-night' : ($q->response ? 'bg-brand text-white' : 'bg-lav text-night/70 hover:bg-lav-deep') }}">{{ $q->position }}</button>
                    @endforeach
                </div>

                <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-night/60">
                    <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-brand"></span>{{ __('exam.legend_answered') }}</li>
                    <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-lav ring-1 ring-lav-deep"></span>{{ __('exam.legend_open') }}</li>
                    <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-sun ring-1 ring-night"></span>{{ __('exam.legend_current') }}</li>
                </ul>
            </div>

            <button type="button" wire:click="submitPart" wire:loading.attr="disabled" wire:target="submitPart"
                wire:confirm="{{ $this->partNumber === 1 ? __('exam.confirm_part', ['n' => 1]) : __('exam.confirm_exam') }}"
                class="w-full rounded-xl bg-linear-to-r from-brand to-brand-deep px-6 py-4 font-bold text-white shadow-[0_10px_30px_-10px_#7C5CFF] transition hover:shadow-[0_14px_36px_-8px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:opacity-60">
                {{ $this->partNumber === 1 ? __('exam.submit_part', ['n' => 1]) : __('exam.submit_exam') }}
            </button>

            <a href="{{ $link('dashboard') }}" class="block text-center text-sm font-bold text-night/60 underline-offset-4 hover:text-night hover:underline sm:hidden">{{ __('exam.exit') }}</a>
        </aside>
    </main>

@elseif ($phase === 'break')
    @php $totals = $this->totals; @endphp
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-5 py-10">
        <div class="relative isolate w-full overflow-hidden rounded-3xl bg-night p-8 text-center text-white sm:p-10">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="absolute -inset-e-16 -top-16 h-64 w-64 rounded-full bg-brand/50 blur-[90px]"></div>
                <div class="absolute -bottom-16 inset-s-0 h-56 w-56 rounded-full bg-mint/25 blur-[90px]"></div>
            </div>

            <h1 class="font-display text-3xl font-extrabold">{{ __('exam.break.title') }}</h1>
            <p class="mx-auto mt-3 max-w-sm leading-relaxed text-white/70">{{ __('exam.break.text') }}</p>

            <div wire:key="break-{{ $endMs }}" x-data='{!! $timerJs !!}' class="mt-8" role="timer" aria-label="{{ __('exam.break_left') }}">
                <p class="text-sm font-medium text-white/60">{{ __('exam.break_left') }}</p>
                <p class="mt-1 font-display text-6xl font-extrabold tabular-nums text-mint" x-text="label" dir="ltr">--:--</p>
            </div>

            <p class="mt-6 text-sm text-white/60">{{ __('exam.break.summary', ['answered' => $totals['part1_answered'], 'total' => $totals['part1_total']]) }}</p>

            <button type="button" wire:click="startPart2" wire:loading.attr="disabled" wire:target="startPart2"
                class="mt-8 w-full rounded-xl bg-white px-6 py-4 font-bold text-night transition hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-night disabled:opacity-70">
                {{ __('exam.break.start_now') }}
            </button>
            <p class="mt-3 text-xs text-white/50">{{ __('exam.break.note', ['minutes' => config('portal.exam.minutes_per_part')]) }}</p>
        </div>
    </main>

@else
    {{-- ===== Done ===== --}}
    @php
        $totals = $this->totals;
        $pct = (float) ($attempt->scorePercent() ?? 0);
        $c = 2 * pi() * 40;
        $ring = $pct < 50 ? '#FF5C85' : ($pct < 70 ? '#7C5CFF' : '#34F5B5');
    @endphp
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-5 py-10">
        <div class="relative isolate w-full overflow-hidden rounded-3xl bg-white p-8 text-center ring-1 ring-lav-deep sm:p-10">
            <div class="pointer-events-none absolute inset-x-0 -top-24 -z-10 mx-auto h-56 w-72 rounded-full bg-mint/40 blur-[80px]" aria-hidden="true"></div>

            <h1 class="font-display text-3xl font-extrabold tracking-tight">{{ __('exam.done.title') }}</h1>
            <p class="mt-2 text-night/65">{{ __('exam.done.text') }}</p>

            <div class="relative mx-auto mt-8 aspect-square w-44" role="img" aria-label="{{ __('exam.done.score') }}: {{ number_format($pct, 0) }}%">
                <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#E4DFFF" stroke-width="10"/>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $ring }}" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ round($c, 1) }}" stroke-dashoffset="{{ round($c * (1 - min(100, $pct) / 100), 1) }}"/>
                </svg>
                <div class="absolute inset-0 grid place-content-center text-center">
                    <span class="font-display text-4xl font-extrabold tabular-nums" dir="ltr">{{ number_format($pct, 0) }}%</span>
                    <span class="text-xs font-medium text-night/55">{{ __('exam.done.score') }}</span>
                </div>
            </div>

            <p class="mt-5 text-sm text-night/60">{{ __('exam.done.answered', ['answered' => $totals['answered'], 'total' => $totals['total']]) }}</p>

            <div class="mt-8 flex flex-col gap-3">
                <a href="{{ $link('attempts.show', [$attempt]) }}" class="rounded-xl bg-linear-to-r from-brand to-brand-deep px-6 py-3.5 font-bold text-white shadow-[0_10px_30px_-10px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('exam.done.report') }}</a>
                <a href="{{ $link('dashboard') }}" class="text-sm font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('exam.done.dashboard') }}</a>
            </div>
        </div>
    </main>
@endif
</div>
