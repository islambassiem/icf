<?php

use App\Models\ExamAttempt;
use App\Services\Exams\ExamClock;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public ExamAttempt $attempt;

    /** all | incorrect | partial | correct | unanswered */
    public string $filter = 'all';

    public function mount(ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id(), 404);

        // If a deadline passed while the user was away, this closes the exam and scores it.
        $this->attempt = app(ExamClock::class)->sync($attempt);

        // The report only exists once the exam is over.
        if (! $this->attempt->completed_at) {
            $this->redirectRoute('exams.take', [$this->attempt]);
        }
    }

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'incorrect', 'partial', 'correct', 'unanswered'], true)) {
            $this->filter = $filter;
        }
    }

    /**
     * One row per question, built from the frozen snapshot and the user's response.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        return $this->attempt->attemptQuestions()
            ->with('response')
            ->orderBy('part_number')
            ->orderBy('position')
            ->get()
            ->map(function ($aq) {
                $snap = $aq->question_snapshot ?? [];
                $options = collect($snap['options'] ?? []);
                $best = (int) ($options->firstWhere('is_best', true)['id'] ?? 0);
                $worst = (int) ($options->firstWhere('is_worst', true)['id'] ?? 0);
                $r = $aq->response;
                $yourBest = $r ? (int) $r->best_answer_option_id : null;
                $yourWorst = $r?->worst_answer_option_id ? (int) $r->worst_answer_option_id : null;

                $points = $r ? (int) ($yourBest === $best) + (int) ($yourWorst === $worst) : 0;

                return [
                    'id' => $aq->id,
                    'part' => $aq->part_number,
                    'position' => $aq->position,
                    'snap' => $snap,
                    'options' => $options,
                    'best' => $best,
                    'worst' => $worst,
                    'your_best' => $yourBest,
                    'your_worst' => $yourWorst,
                    'points' => $points,
                    'status' => ! $r ? 'unanswered' : ($points === 2 ? 'correct' : ($points === 1 ? 'partial' : 'incorrect')),
                ];
            });
    }

    #[Computed]
    public function counts(): array
    {
        $rows = $this->rows;

        return [
            'all' => $rows->count(),
            'incorrect' => $rows->where('status', 'incorrect')->count(),
            'partial' => $rows->where('status', 'partial')->count(),
            'correct' => $rows->where('status', 'correct')->count(),
            'unanswered' => $rows->where('status', 'unanswered')->count(),
        ];
    }

    #[Computed]
    public function visible(): Collection
    {
        return $this->filter === 'all' ? $this->rows : $this->rows->where('status', $this->filter)->values();
    }

    #[Computed]
    public function breakdown(): array
    {
        return $this->attempt->breakdown();
    }

    /** Score of the previous completed exam of the same type, to show progress. */
    #[Computed]
    public function previousScore(): ?float
    {
        return ExamAttempt::query()
            ->where('user_id', $this->attempt->user_id)
            ->where('exam_type_id', $this->attempt->exam_type_id)
            ->completed()
            ->whereNotNull('score')
            ->where('completed_at', '<', $this->attempt->completed_at)
            ->latest('completed_at')
            ->first()
            ?->scorePercent();
    }
};
?>

<div>
@php
    $attempt = $this->attempt;
    $rows = $this->rows;
    $counts = $this->counts;
    $breakdown = $this->breakdown;
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
    $ar = app()->getLocale() === 'ar';
    $text = fn (array $row, string $field) => $ar ? (($row[$field . '_ar'] ?? '') ?: ($row[$field . '_en'] ?? '')) : (($row[$field . '_en'] ?? '') ?: ($row[$field . '_ar'] ?? ''));

    $pct = (float) ($attempt->scorePercent() ?? 0);
    $points = $rows->sum('points');
    $maxPoints = $rows->count() * 2;
    $prev = $this->previousScore;
    $delta = $prev === null ? null : round($pct - $prev);
    $minutes = $attempt->started_at && $attempt->completed_at ? (int) round($attempt->started_at->diffInMinutes($attempt->completed_at)) : null;
    $typeName = $attempt->examType?->name;

    $c = 2 * pi() * 40;
    $ring = $pct < 50 ? '#FF5C85' : ($pct < 70 ? '#B9A8FF' : '#34F5B5');
    $tone = fn ($v) => $v < 50 ? 'bg-coral' : ($v < 70 ? 'bg-brand' : 'bg-mint');
    $card = 'rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-7';

    // Lowest two profile types and the lowest question area: the "where to focus" list.
    $focus = collect($breakdown['profile'])->sortBy('percent')->take(2)
        ->merge(collect($breakdown['area'])->sortBy('percent')->take(1))
        ->filter(fn ($g) => $g['percent'] < 80)->values();
    $weakProfileId = collect($breakdown['profile'])->sortBy('percent')->first()['id'] ?? null;

    $statusStyle = [
        'correct' => 'bg-mint/20 text-mint-deep',
        'partial' => 'bg-sun/30 text-night',
        'incorrect' => 'bg-coral/15 text-coral-deep',
        'unanswered' => 'bg-lav-deep text-night',
    ];
@endphp

{{-- ===== Top row ===== --}}
<div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
    <a href="{{ $link('attempts.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-brand-deep underline-offset-4 hover:underline">
        <svg viewBox="0 0 24 24" class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
        {{ __('report.show.back') }}
    </a>
    <div class="flex flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="rounded-xl px-4 py-2.5 text-sm font-bold ring-1 ring-lav-deep transition hover:ring-brand focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('report.show.print') }}</button>
        <a href="{{ $link('exams.index') }}" class="rounded-xl bg-night px-5 py-2.5 text-sm font-bold text-white hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('report.show.retake') }}</a>
    </div>
</div>

{{-- ===== Summary ===== --}}
<section class="relative isolate mt-5 overflow-hidden rounded-3xl bg-night p-7 text-white sm:p-10 print:bg-white print:text-night print:ring-1 print:ring-night/20" aria-labelledby="report-title">
    <div class="pointer-events-none absolute inset-0 -z-10 print:hidden" aria-hidden="true">
        <div class="bg-grid absolute inset-0 opacity-60"></div>
        <div class="absolute -inset-e-16-top-20 h-72 w-72 rounded-full bg-brand/50 blur-[90px]"></div>
        <div class="absolute -bottom-20 inset-s-1/4 h-60 w-60 rounded-full bg-mint/20 blur-[90px]"></div>
    </div>

    <div class="grid items-center gap-8 md:grid-cols-[auto_1fr]">
        <div class="relative mx-auto aspect-square w-44 shrink-0" role="img" aria-label="{{ __('report.show.score') }}: {{ number_format($pct, 0) }}%">
            <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="10"/>
                <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $ring }}" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ round($c, 1) }}" stroke-dashoffset="{{ round($c * (1 - min(100, $pct) / 100), 1) }}"/>
            </svg>
            <div class="absolute inset-0 grid place-content-center text-center">
                <span class="font-display text-5xl font-extrabold tabular-nums" dir="ltr">{{ number_format($pct, 0) }}%</span>
                <span class="text-xs font-medium text-white/60 print:text-night/60">{{ __('report.show.score') }}</span>
            </div>
        </div>

        <div>
            <h1 id="report-title" class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('report.show.heading', ['type' => $typeName]) }}</h1>
            <p class="mt-1.5 text-white/65 print:text-night/60">{{ __('report.show.taken', ['date' => $attempt->completed_at->translatedFormat('j M Y')]) }}</p>

            <p class="mt-4 inline-block rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold print:bg-lav">
                @if ($delta === null)
                    {{ __('report.show.first', ['type' => $typeName]) }}
                @else
                    <span class="{{ $delta >= 0 ? 'text-mint' : 'text-coral' }} font-extrabold" dir="ltr">{{ $delta > 0 ? '+' : '' }}{{ $delta }}</span>
                    {{ __('report.show.vs_prev', ['type' => $typeName]) }}
                @endif
            </p>

            <dl class="mt-6 grid grid-cols-3 gap-4 text-sm">
                <div><dt class="text-white/55 print:text-night/55">{{ __('report.show.points', ['n' => $points, 'max' => $maxPoints]) }}</dt></div>
                <div><dt class="text-white/55 print:text-night/55">{{ __('report.show.answered', ['n' => $rows->count() - $counts['unanswered'], 'total' => $rows->count()]) }}</dt></div>
                @if ($minutes !== null)
                    <div><dt class="text-white/55 print:text-night/55">{{ __('report.show.time') }}: <span class="font-bold text-white print:text-night">{{ __('report.show.minutes', ['n' => $minutes]) }}</span></dt></div>
                @endif
            </dl>
        </div>
    </div>
</section>

{{-- ===== Breakdown + focus ===== --}}
<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <section class="{{ $card }} lg:col-span-2" aria-labelledby="groups-title">
        <h2 id="groups-title" class="sr-only">{{ __('report.groups.by_profile') }}</h2>
        <div class="grid gap-8 sm:grid-cols-2">
            @foreach (['profile' => 'by_profile', 'area' => 'by_area'] as $group => $heading)
                @if (! empty($breakdown[$group]))
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-night/45">{{ __('report.groups.' . $heading) }}</h3>
                        <div class="mt-4 space-y-4">
                            @foreach ($breakdown[$group] as $row)
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                                        <span class="font-semibold">
                                            {{ $row['name'] }}
                                            @if ($group === 'profile' && $row['id'] === $weakProfileId && count($breakdown['profile']) > 1)
                                                <span class="ms-1 rounded-full bg-coral/15 px-2 py-0.5 text-[11px] font-bold text-coral-deep">{{ __('report.groups.weakest') }}</span>
                                            @endif
                                        </span>
                                        <span class="font-bold tabular-nums" dir="ltr">{{ $row['percent'] }}%</span>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-lav-deep" role="img" aria-label="{{ $row['name'] }}: {{ $row['percent'] }}%">
                                        <div class="h-full rounded-full {{ $tone($row['percent']) }}" style="width: {{ min(100, max(0, $row['percent'])) }}%"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-night/45">{{ trans_choice('report.groups.questions', $row['count'], ['count' => $row['count']]) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-brand to-brand-deep p-7 text-white print:bg-white print:text-night print:ring-1 print:ring-night/20" aria-labelledby="focus-title">
        <div class="pointer-events-none absolute -inset-e-10 -top-10 h-40 w-40 rounded-full bg-mint/30 blur-3xl print:hidden" aria-hidden="true"></div>
        <h2 id="focus-title" class="relative font-display text-xl font-bold">{{ __('report.focus.title') }}</h2>
        @if ($focus->isNotEmpty())
            <p class="relative mt-2 text-sm leading-relaxed text-white/80 print:text-night/70">{{ __('report.focus.lead') }}</p>
            <ul class="relative mt-5 space-y-2.5">
                @foreach ($focus as $g)
                    <li class="flex items-center justify-between gap-3 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold print:bg-lav">
                        <span>{{ $g['name'] }}</span>
                        <span class="tabular-nums" dir="ltr">{{ $g['percent'] }}%</span>
                    </li>
                @endforeach
            </ul>
            <button type="button" wire:click="setFilter('incorrect')" onclick="setTimeout(() => document.getElementById('review')?.scrollIntoView({ behavior: 'smooth' }), 300)"
                class="relative mt-5 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-night transition hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-deep print:hidden">{{ __('report.focus.review') }}</button>
        @else
            <p class="relative mt-2 text-sm leading-relaxed text-white/80 print:text-night/70">{{ __('report.focus.all_good') }}</p>
        @endif
    </section>
</div>

{{-- ===== Question by question ===== --}}
<section id="review" class="mt-10 scroll-mt-24" aria-labelledby="review-title">
    <h2 id="review-title" class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{{ __('report.review.title') }}</h2>
    <p class="mt-1.5 max-w-2xl text-night/65">{{ __('report.review.lead') }}</p>

    <div class="mt-5 flex flex-wrap gap-2 print:hidden" role="tablist" aria-label="{{ __('report.review.title') }}">
        @foreach (['all', 'incorrect', 'partial', 'correct', 'unanswered'] as $f)
            @if ($f === 'all' || $counts[$f] > 0)
                <button type="button" role="tab" aria-selected="{{ $filter === $f ? 'true' : 'false' }}" wire:click="setFilter('{{ $f }}')"
                    class="rounded-full px-4 py-2 text-sm font-bold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand
                           {{ $filter === $f ? 'bg-night text-white' : 'bg-white text-night ring-1 ring-lav-deep hover:ring-brand' }}">
                    {{ __('report.review.filter.' . $f) }}
                    <span class="ms-1 tabular-nums {{ $filter === $f ? 'text-white/70' : 'text-night/45' }}">{{ $counts[$f] }}</span>
                </button>
            @endif
        @endforeach
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($this->visible as $row)
            <article wire:key="row-{{ $row['id'] }}" x-data="{ open: false }" class="rounded-2xl bg-white ring-1 ring-lav-deep">
                <h3>
                    <button type="button" x-on:click="open = !open" :aria-expanded="open"
                        class="flex w-full items-start gap-4 rounded-2xl px-5 py-4 text-start focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full font-display text-sm font-extrabold {{ $statusStyle[$row['status']] }}">{{ $row['position'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-night/45">{{ __('report.review.part_q', ['part' => $row['part'], 'n' => $row['position']]) }}</span>
                            <span class="mt-0.5 block font-semibold leading-relaxed">{{ $text($row['snap'], 'statement') }}</span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1.5">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusStyle[$row['status']] }}">{{ __('report.review.status.' . $row['status']) }}</span>
                            @if ($row['status'] !== 'unanswered')
                                <span class="text-xs tabular-nums text-night/50">{{ __('report.review.points', ['n' => $row['points']]) }}</span>
                            @endif
                        </span>
                        <svg viewBox="0 0 24 24" class="mt-1.5 h-5 w-5 shrink-0 text-night/40 transition print:hidden" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </h3>

                <div x-show="open" x-collapse x-cloak class="print:block! space-y-3 border-t border-lav-deep px-5 pb-5 pt-4">
                    @if ($row['status'] === 'unanswered')
                        <p class="rounded-xl bg-lav px-4 py-2.5 text-sm font-medium">{{ __('report.review.not_answered') }}</p>
                    @endif

                    @foreach ($row['options'] as $i => $o)
                        @php
                            $id = (int) $o['id'];
                            $isBest = $id === $row['best'];
                            $isWorst = $id === $row['worst'];
                            $yourBest = $id === $row['your_best'];
                            $yourWorst = $id === $row['your_worst'];
                            $comment = trim($text($o, 'comment'));
                        @endphp
                        <div class="rounded-xl border-2 px-4 py-3 {{ $isBest ? 'border-mint bg-mint/10' : ($isWorst ? 'border-coral bg-coral/10' : (($yourBest || $yourWorst) ? 'border-sun bg-sun/15' : 'border-lav-deep')) }}">
                            <div class="flex items-start gap-3">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white font-display text-xs font-extrabold ring-1 ring-lav-deep">{{ $i + 1 }}</span>
                                <p class="flex-1 leading-relaxed">{{ $text($o, 'statement') }}</p>
                            </div>

                            @if ($isBest || $isWorst || $yourBest || $yourWorst)
                                <div class="mt-2 flex flex-wrap gap-1.5 ps-9 text-[11px] font-bold">
                                    @if ($isBest)<span class="rounded-full bg-mint px-2.5 py-0.5 text-night">{{ __('report.review.best_answer') }}</span>@endif
                                    @if ($isWorst)<span class="rounded-full bg-coral px-2.5 py-0.5 text-white">{{ __('report.review.worst_answer') }}</span>@endif
                                    @if ($yourBest)<span class="rounded-full bg-white px-2.5 py-0.5 ring-1 {{ $isBest ? 'text-mint-deep ring-mint-deep/40' : 'text-coral-deep ring-coral-deep/40' }}">{{ $isBest ? '✓' : '✗' }} {{ __('report.review.your_best') }}</span>@endif
                                    @if ($yourWorst)<span class="rounded-full bg-white px-2.5 py-0.5 ring-1 {{ $isWorst ? 'text-mint-deep ring-mint-deep/40' : 'text-coral-deep ring-coral-deep/40' }}">{{ $isWorst ? '✓' : '✗' }} {{ __('report.review.your_worst') }}</span>@endif
                                </div>
                            @endif

                            @if ($comment !== '')
                                <p class="mt-2 ps-9 text-sm leading-relaxed text-night/70"><span class="font-bold text-night/85">{{ __('report.review.comment') }}:</span> {{ $comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-white p-8 text-center text-sm text-night/60 ring-1 ring-lav-deep">{{ __('report.review.empty') }}</p>
        @endforelse
    </div>
</section>
</div>
