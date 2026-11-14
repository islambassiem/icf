<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::portal')] class extends Component {
    use WithPagination;

    #[Computed]
    public function attempts()
    {
        return auth()->user()->examAttempts()->with('examType')->latest('started_at')->paginate(10);
    }

    /** Scores as 0-100 percentages. */
    #[Computed]
    public function stats(): array
    {
        $done = fn () => auth()->user()->examAttempts()->completed()->whereNotNull('score');
        $max = max(1, (float) config('portal.score_max'));
        $pct = fn ($v) => $v === null ? null : round($v / $max * 100);

        return [
            'count' => auth()->user()->examAttempts()->completed()->count(),
            'best' => $pct($done()->max('score')),
            'average' => $pct($done()->avg('score')),
        ];
    }
};
?>

<div>
@php
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
    $stats = $this->stats;
    $badge = fn (string $s) => match ($s) {
        'completed' => 'bg-mint/20 text-mint-deep',
        'in_progress' => 'bg-sun/30 text-night',
        'expired' => 'bg-coral/15 text-coral-deep',
        default => 'bg-lav-deep text-night',
    };
    $tone = fn ($v) => $v < 50 ? 'text-coral-deep' : ($v < 70 ? 'text-brand-deep' : 'text-mint-deep');
    $card = 'rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-7';
@endphp

<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('report.index.heading') }}</h1>
        <p class="mt-1.5 text-night/65">{{ __('report.index.lead') }}</p>
    </div>
    <a href="{{ $link('exams.index') }}" class="rounded-full bg-night px-6 py-3 text-sm font-bold text-white transition hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('report.index.start') }}</a>
</div>

<dl class="mt-8 grid grid-cols-3 gap-4">
    @foreach ([['count', (string) $stats['count']], ['best', $stats['best'] === null ? '-' : $stats['best'] . '%'], ['average', $stats['average'] === null ? '-' : $stats['average'] . '%']] as [$key, $value])
        <div class="{{ $card }} p-5!">
            <dt class="text-sm font-medium text-night/60">{{ __('report.index.stats.' . $key) }}</dt>
            <dd class="mt-1.5 font-display text-3xl font-extrabold tabular-nums" dir="ltr">{{ $value }}</dd>
        </div>
    @endforeach
</dl>

<section class="{{ $card }} mt-5">
    @if ($this->attempts->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full min-w-152 text-start text-sm">
                <thead class="text-xs font-bold uppercase text-night/45">
                    <tr class="border-b border-lav-deep">
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('report.index.date') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('report.index.type') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('report.index.score') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('report.index.time') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('report.index.status') }}</th>
                        <th class="py-2.5 text-end font-bold"><span class="sr-only">{{ __('report.index.report') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-lav-deep/70">
                    @foreach ($this->attempts as $a)
                        @php
                            $state = $a->state();
                            $pct = $a->scorePercent();
                            $mins = $a->completed_at ? (int) round($a->started_at->diffInMinutes($a->completed_at)) : null;
                        @endphp
                        <tr wire:key="attempt-{{ $a->id }}">
                            <td class="whitespace-nowrap py-3.5 pe-3">{{ $a->started_at->translatedFormat('j M Y') }}</td>
                            <td class="py-3.5 pe-3 font-display font-bold">{{ $a->examType?->name }}</td>
                            <td class="py-3.5 pe-3 font-extrabold tabular-nums {{ $pct !== null ? $tone($pct) : '' }}" dir="ltr">{{ $pct !== null ? number_format($pct, 0) . '%' : '-' }}</td>
                            <td class="whitespace-nowrap py-3.5 pe-3 text-night/65">{{ $mins !== null ? __('report.index.minutes', ['n' => $mins]) : '-' }}</td>
                            <td class="py-3.5 pe-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $badge($state) }}">{{ __('report.index.status_names.' . $state) }}</span></td>
                            <td class="py-3.5 text-end">
                                @if ($state === 'in_progress')
                                    <a href="{{ $link('exams.take', [$a]) }}" class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('report.index.resume') }}</a>
                                @else
                                    {{-- Opening an expired attempt closes and scores it first. --}}
                                    <a href="{{ $link('attempts.show', [$a]) }}" class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('report.index.report') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->attempts->links() }}</div>
    @else
        <div class="rounded-2xl bg-lav p-8 text-center">
            <p class="text-night/60">{{ __('report.index.empty') }}</p>
            <a href="{{ $link('exams.index') }}" class="mt-3 inline-block text-sm font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('report.index.start') }}</a>
        </div>
    @endif
</section>
</div>
