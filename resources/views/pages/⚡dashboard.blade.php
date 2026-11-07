<?php

use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public ?int $examTypeId = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->examTypeId = $this->examTypes->first()?->id;
    }

    public function setExamType(int $id): void
    {
        if ($this->examTypes->contains('id', $id)) {
            $this->examTypeId = $id;
            $this->notice = null;
        }
    }

    /** ACC, PCC, ... from the exam_types table. */
    #[Computed]
    public function examTypes(): Collection
    {
        return ExamType::orderBy('id')->get();
    }

    /** The paid period running right now, if any. */
    #[Computed]
    public function subscription()
    {
        return auth()->user()->activeSubscription()->with('subscriptionPlan')->first();
    }

    /** Most recent subscription of any kind, used to show "expired on ...". */
    #[Computed]
    public function lastSubscription()
    {
        return auth()->user()->subscriptions()->with('subscriptionPlan')->where('status', '!=', Subscription::STATUS_PENDING)->latest('ends_at')->first();
    }

    #[Computed]
    public function inProgress(): ?ExamAttempt
    {
        return auth()
            ->user()
            ->examAttempts()
            ->inProgress()
            ->with('examType')
            ->withCount(['attemptQuestions as total_questions', 'responses as answered_count'])
            ->latest('started_at')
            ->first();
    }

    #[Computed]
    public function attempts(): Collection
    {
        return auth()->user()->examAttempts()->with('examType')->latest('started_at')->limit(5)->get();
    }

    /** Last completed exams, oldest first, for the trend chart. */
    #[Computed]
    public function completed(): Collection
    {
        return auth()->user()->examAttempts()->completed()->whereNotNull('score')->with('examType')->latest('completed_at')->limit(8)->get()->reverse()->values();
    }

    #[Computed]
    public function payments(): Collection
    {
        return auth()->user()->payments()->with('subscription.subscriptionPlan')->latest('payments.created_at')->limit(5)->get();
    }

    #[Computed]
    public function plans(): Collection
    {
        return SubscriptionPlan::active()->orderBy('duration_days')->get();
    }

    /** Scores as 0-100 percentages. */
    #[Computed]
    public function stats(): array
    {
        $done = fn() => auth()->user()->examAttempts()->completed()->whereNotNull('score');
        $max = max(1, (float) config('portal.score_max'));
        $pct = fn($v) => $v === null ? null : round(($v / $max) * 100, 1);

        return [
            'count' => auth()->user()->examAttempts()->completed()->count(),
            'best' => $pct($done()->max('score')),
            'average' => $pct($done()->avg('score')),
            'latest' => $pct($done()->latest('completed_at')->value('score')),
        ];
    }

    /** Results by profile type and question area for the latest completed exam. */
    #[Computed]
    public function breakdown(): ?array
    {
        $latest = auth()->user()->examAttempts()->completed()->latest('completed_at')->first();

        if (!$latest) {
            return null;
        }

        $b = $latest->breakdown();

        return $b['profile'] || $b['area'] ? $b : null;
    }

    /** Lowest profile type in the latest exam. */
    #[Computed]
    public function weakest(): ?array
    {
        $profile = collect($this->breakdown['profile'] ?? []);

        return $profile->isEmpty() ? null : $profile->sortBy('percent')->first();
    }

    /** The single most useful thing to do right now. */
    #[Computed]
    public function next(): array
    {
        if (!$this->subscription) {
            return ['key' => 'subscribe', 'params' => []];
        }

        if ($attempt = $this->inProgress) {
            return [
                'key' => 'resume',
                'params' => [
                    'part' => $attempt->currentPart(),
                    'answered' => $attempt->answered_count,
                    'total' => $attempt->total_questions,
                ],
            ];
        }

        if ($this->stats['count'] > 0 && ($weak = $this->weakest)) {
            return ['key' => 'improve', 'params' => ['area' => $weak['name'], 'score' => $weak['percent']]];
        }

        return ['key' => 'first', 'params' => []];
    }

    /** Show the plan picker when there is no plan, or the current one ends soon. */
    #[Computed]
    public function showPlans(): bool
    {
        $sub = $this->subscription;

        return $this->plans->isNotEmpty() && (!$sub || $sub->daysLeft() <= config('portal.renew_warning_days'));
    }

    /** Hands over to the exam page, which picks the questions and creates the attempt. */
    public function startExam(): void
    {
        $this->notice = null;

        if (!$this->subscription) {
            $this->notice = __('portal.dashboard.exam.locked');

            return;
        }

        if (!Route::has('exams.create')) {
            $this->notice = __('portal.dashboard.exam.coming');

            return;
        }

        $this->redirectRoute('exams.create', ['examType' => $this->examTypeId], navigate: true);
    }
};
?>

<div>
    @php
        // Safe list lookup: a missing translation file gives an empty list instead of crashing the page.
        $list = fn(string $key): array => is_array($v = __($key)) ? $v : [];
        $link = fn(string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
        $user = auth()->user();
        $sub = $this->subscription;
        $last = $this->lastSubscription;
        $in = $this->inProgress;
        $stats = $this->stats;
        $next = $this->next;
        $weakest = $this->weakest;
        $breakdown = $this->breakdown;
        $completed = $this->completed;

        $hour = now()->hour;
        $greetKey = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
        $firstName = \Illuminate\Support\Str::before(trim($user->name), ' ');

        $pct = fn($v) => $v === null ? __('portal.dashboard.stats.none') : number_format($v, 0) . '%';
        $tone = fn($v) => $v < 50 ? 'bg-coral' : ($v < 70 ? 'bg-brand' : 'bg-mint');
        $badge = fn(string $s) => match ($s) {
            'completed', 'paid' => 'bg-mint/20 text-mint-deep',
            'in_progress', 'pending' => 'bg-sun/30 text-night',
            'failed', 'expired' => 'bg-coral/15 text-coral-deep',
            default => 'bg-lav-deep text-night',
        };
        // Translated status text; unknown statuses fall back to a readable version of the raw value.
        $statusLabel = fn(string $group, string $key) => \Illuminate\Support\Facades\Lang::has(
            "portal.dashboard.{$group}.status_names.{$key}",
        )
            ? __("portal.dashboard.{$group}.status_names.{$key}")
            : \Illuminate\Support\Str::headline($key);
        $num = fn($n) => number_format((float) $n, fmod((float) $n, 1) ? 2 : 0);

        $nextHref = match ($next['key']) {
            'subscribe' => '#plans',
            'resume' => $link('exams.take', [$in]),
            default => '#start-exam',
        };

        $icons = [
            'arrow' => 'M5 12h14M13 6l6 6-6 6',
            'check' => 'M5 13l4 4L19 7',
            'lock' =>
                'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
            'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        ];

        $card = 'rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-7';
    @endphp

    {{-- ============ HEADER ============ --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">
                {{ __('portal.dashboard.greeting.' . $greetKey, ['name' => $firstName]) }}</h1>
            <p class="mt-1.5 text-night/65">{{ __('portal.dashboard.subtitle') }}</p>
        </div>
        <a href="#start-exam"
            class="inline-flex items-center gap-2 rounded-full bg-night px-6 py-3 text-sm font-bold text-white transition hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
            {{ __('portal.dashboard.start_exam') }}
            <svg viewBox="0 0 24 24" class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $icons['arrow'] }}" />
            </svg>
        </a>
    </div>

    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-sun/25 px-5 py-4 text-sm font-medium ring-1 ring-sun/60"
            role="status">
            <p>{{ __('portal.dashboard.verify.text') }}</p>
            <a href="{{ $link('verification.notice') }}"
                class="font-bold underline underline-offset-4">{{ __('portal.dashboard.verify.action') }}</a>
        </div>
    @endif

    {{-- ============ NEXT STEP + SUBSCRIPTION ============ --}}
    <div class="mt-8 grid gap-5 lg:grid-cols-3">
        <section class="relative isolate overflow-hidden rounded-3xl bg-night p-7 text-white sm:p-9 lg:col-span-2"
            aria-labelledby="next-title">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="bg-grid absolute inset-0 opacity-60"></div>
                <div class="absolute -inset-e-16 -top-16 h-72 w-72 rounded-full bg-brand/50 blur-[90px]"></div>
                <div class="absolute -bottom-20 inset-s-1/4 h-60 w-60 rounded-full bg-mint/20 blur-[90px]"></div>
            </div>
            <p
                class="glass inline-flex items-center gap-2 rounded-full px-3.5 py-1 text-xs font-bold tracking-wide text-white/85">
                <span class="h-2 w-2 rounded-full bg-mint shadow-[0_0_10px_#34F5B5]"></span>
                {{ __('portal.dashboard.next.label') }}
            </p>
            <h2 id="next-title"
                class="mt-5 max-w-xl font-display text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
                {{ __('portal.dashboard.next.' . $next['key'] . '.title', $next['params']) }}
            </h2>
            <p class="mt-3 max-w-xl leading-relaxed text-white/70">
                {{ __('portal.dashboard.next.' . $next['key'] . '.text', $next['params']) }}
            </p>
            <a href="{{ $nextHref }}"
                class="mt-7 inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 font-bold text-night transition hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-night">
                {{ __('portal.dashboard.next.' . $next['key'] . '.cta') }}
                <svg viewBox="0 0 24 24" class="h-5 w-5 rtl:rotate-180" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $icons['arrow'] }}" />
                </svg>
            </a>
        </section>

        {{-- Subscription --}}
        <section class="{{ $card }} flex flex-col" aria-labelledby="sub-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="sub-title" class="font-display text-lg font-bold">{{ __('portal.dashboard.sub.title') }}</h2>
                @if ($sub)
                    <span
                        class="rounded-full bg-mint/20 px-3 py-1 text-xs font-bold text-mint-deep">{{ __('portal.dashboard.sub.active') }}</span>
                @elseif ($last)
                    <span
                        class="rounded-full bg-coral/15 px-3 py-1 text-xs font-bold text-coral-deep">{{ __('portal.dashboard.sub.expired') }}</span>
                @else
                    <span
                        class="rounded-full bg-lav-deep px-3 py-1 text-xs font-bold">{{ __('portal.dashboard.sub.none') }}</span>
                @endif
            </div>

            @if ($sub)
                <p class="mt-5 text-sm font-medium text-night/60">
                    {{ __('portal.dashboard.sub.plan', ['plan' => $sub->subscriptionPlan?->name]) }}</p>
                <p class="mt-1 font-display text-3xl font-extrabold">
                    {{ trans_choice('portal.dashboard.sub.days_left', $sub->daysLeft(), ['count' => $sub->daysLeft()]) }}
                </p>
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-lav-deep" role="presentation">
                    <div class="h-full rounded-full bg-linear-to-r from-brand to-mint rtl:bg-linear-to-l"
                        style="width: {{ $sub->elapsedPercent() }}%"></div>
                </div>
                <p class="mt-3 text-sm text-night/60">
                    {{ __('portal.dashboard.sub.valid_until', ['date' => $sub->ends_at->translatedFormat('j M Y')]) }}
                </p>

                @if ($this->showPlans)
                    <p class="mt-4 rounded-xl bg-sun/25 px-3.5 py-2.5 text-sm font-medium">
                        {{ __('portal.dashboard.sub.expiring') }}</p>
                    <a href="#plans" class="mt-auto pt-5"><span
                            class="block rounded-xl bg-night py-3 text-center text-sm font-bold text-white hover:bg-night-3">{{ __('portal.dashboard.sub.renew') }}</span></a>
                @else
                    <a href="{{ $link('subscription.index') }}"
                        class="mt-auto pt-5 text-sm font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.sub.manage') }}</a>
                @endif
            @else
                <p class="mt-5 text-night/65">
                    @if ($last)
                        {{ __('portal.dashboard.sub.expired_on', ['date' => $last->ends_at->translatedFormat('j M Y')]) }}
                    @else
                        {{ __('portal.dashboard.sub.none_text') }}
                    @endif
                </p>
                <a href="#plans" class="mt-auto pt-6"><span
                        class="block rounded-xl bg-linear-to-r from-brand to-brand-deep py-3 text-center text-sm font-bold text-white">{{ $last ? __('portal.dashboard.sub.renew') : __('portal.dashboard.sub.subscribe') }}</span></a>
            @endif
        </section>
    </div>

    {{-- ============ STATS ============ --}}
    <dl class="mt-5 grid grid-cols-2 gap-5 lg:grid-cols-4">
        @foreach ([['attempts', (string) $stats['count'], 'bg-brand'], ['best', $pct($stats['best']), 'bg-mint'], ['average', $pct($stats['average']), 'bg-sun'], ['latest', $pct($stats['latest']), 'bg-coral']] as [$key, $value, $dot])
            <div class="{{ $card }} p-5!">
                <dt class="flex items-center gap-2 text-sm font-medium text-night/60"><span
                        class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>{{ __('portal.dashboard.stats.' . $key) }}
                </dt>
                <dd class="mt-2 font-display text-4xl font-extrabold tabular-nums" dir="ltr">{{ $value }}
                </dd>
            </div>
        @endforeach
    </dl>

    {{-- ============ PLANS (no plan, or ending soon) ============ --}}
    @if ($this->showPlans)
        <section id="plans" class="mt-10 scroll-mt-24" aria-labelledby="plans-title">
            <h2 id="plans-title" class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">
                {{ __('portal.dashboard.plans.title') }}</h2>
            <p class="mt-1.5 max-w-2xl text-night/65">{{ __('portal.dashboard.plans.lead') }}</p>
            <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->plans as $plan)
                    @php $popular = (bool) ($plan->is_popular ?? false); @endphp
                    <article
                        class="relative flex flex-col rounded-3xl p-7 {{ $popular ? 'bg-night text-white shadow-xl shadow-brand/25 ring-2 ring-brand' : 'bg-white ring-1 ring-lav-deep' }}">
                        @if ($popular)
                            <span
                                class="absolute -top-3 inset-s-7 rounded-full bg-linear-to-r from-brand to-mint px-3 py-1 text-xs font-extrabold text-night">{{ __('portal.dashboard.plans.popular') }}</span>
                        @endif
                        <h3 class="font-display text-xl font-bold">{{ $plan->name }}</h3>
                        <p class="mt-1 text-sm {{ $popular ? 'text-white/65' : 'text-night/55' }}">
                            {{ trans_choice('portal.dashboard.plans.days', $plan->duration_days, ['count' => $plan->duration_days]) }}
                        </p>
                        <p class="mt-5 flex items-baseline gap-1.5" dir="ltr">
                            <span
                                class="font-display text-5xl font-extrabold tracking-tight">{{ $num($plan->price) }}</span>
                            <span
                                class="text-sm font-bold {{ $popular ? 'text-white/65' : 'text-night/55' }}">{{ $plan->currencyCode() }}</span>
                        </p>
                        <ul class="mt-6 space-y-2.5 text-sm">
                            @foreach ($list('portal.dashboard.plans.features') as $feature)
                                <li class="flex items-start gap-2.5">
                                    <svg viewBox="0 0 24 24"
                                        class="mt-0.5 h-4 w-4 shrink-0 {{ $popular ? 'text-mint' : 'text-mint-deep' }}"
                                        fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="{{ $icons['check'] }}" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ $link('subscription.checkout', ['plan' => $plan->id]) }}"
                            class="mt-7 block rounded-xl py-3 text-center text-sm font-bold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 {{ $popular ? 'bg-white text-night hover:bg-mint' : 'bg-night text-white hover:bg-night-3' }}">
                            {{ __('portal.dashboard.plans.choose') }}
                        </a>
                    </article>
                @endforeach
            </div>
            <p class="mt-4 flex items-center gap-2 text-sm text-night/55">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $icons['lock'] }}" />
                </svg>
                {{ __('portal.dashboard.plans.secure') }}
            </p>
        </section>
    @endif

    {{-- ============ START EXAM + FOCUS ============ --}}
    <div class="mt-10 grid gap-5 lg:grid-cols-3">
        <section id="start-exam" class="{{ $card }} scroll-mt-24 lg:col-span-2"
            aria-labelledby="exam-title">
            <h2 id="exam-title" class="font-display text-2xl font-extrabold tracking-tight">
                {{ __('portal.dashboard.exam.title') }}</h2>

            @if ($in)
                {{-- Resume panel --}}
                @php $deadline = $in->currentDeadline(); @endphp
                <div class="mt-5 rounded-2xl bg-sun/20 p-5 ring-1 ring-sun/60">
                    <p class="font-display text-lg font-bold">{{ __('portal.dashboard.exam.resume_title') }}</p>
                    <p class="mt-1 text-sm text-night/75">
                        {{ __('portal.dashboard.exam.resume_text', ['type' => $in->examType?->name, 'part' => $in->currentPart(), 'answered' => $in->answered_count, 'total' => $in->total_questions]) }}
                    </p>
                    <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-brand"
                            style="width: {{ $in->total_questions ? round(($in->answered_count / $in->total_questions) * 100) : 0 }}%">
                        </div>
                    </div>
                    @if ($deadline)
                        <p class="mt-3 flex items-center gap-2 text-sm font-medium">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <path d="{{ $icons['clock'] }}" />
                            </svg>
                            {{ $in->onBreak() ? __('portal.dashboard.exam.break_left') : __('portal.dashboard.exam.time_left') }}:
                            {{ __('portal.dashboard.attempts.minutes', ['n' => max(0, (int) now()->diffInMinutes($deadline, false))]) }}
                        </p>
                    @endif
                    <a href="{{ $link('exams.take', [$in]) }}"
                        class="mt-5 inline-block rounded-xl bg-night px-6 py-3 text-sm font-bold text-white hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('portal.dashboard.exam.resume') }}</a>
                </div>
            @else
                <p class="mt-2 max-w-2xl text-night/65">{{ __('portal.dashboard.exam.lead') }}</p>

                <div class="mt-6 grid gap-3 sm:grid-cols-2" role="radiogroup"
                    aria-label="{{ __('portal.dashboard.exam.title') }}">
                    @foreach ($this->examTypes as $type)
                        @php $selected = $examTypeId === $type->id; @endphp
                        <button type="button" role="radio" aria-checked="{{ $selected ? 'true' : 'false' }}"
                            wire:click="setExamType({{ $type->id }})"
                            class="rounded-2xl border-2 p-5 text-start transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand
                               {{ $selected ? 'border-brand bg-brand/8 shadow-lg shadow-brand/10' : 'border-lav-deep hover:border-brand/40' }}">
                            <span class="flex items-center justify-between gap-3">
                                <span class="font-display text-2xl font-extrabold">{{ $type->name }}</span>
                                <span
                                    class="grid h-6 w-6 shrink-0 place-items-center rounded-full border-2 {{ $selected ? 'border-brand bg-brand text-white' : 'border-lav-deep' }}">
                                    @if ($selected)
                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none"
                                            stroke="currentColor" stroke-width="3.5" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="{{ $icons['check'] }}" />
                                        </svg>
                                    @endif
                                </span>
                            </span>
                            @if ($type->description)
                                <span class="mt-1 block text-sm text-night/60">{{ $type->description }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <ul class="mt-5 flex flex-wrap gap-2 text-xs font-bold">
                    @foreach ($list('portal.dashboard.exam.facts') as $fact)
                        <li class="rounded-full bg-lav px-3.5 py-1.5">{{ $fact }}</li>
                    @endforeach
                </ul>

                <ul class="mt-5 space-y-2 text-sm text-night/70">
                    @foreach ($list('portal.dashboard.exam.rules') as $rule)
                        <li class="flex items-start gap-2.5">
                            <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0 text-mint-deep" fill="none"
                                stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="{{ $icons['check'] }}" />
                            </svg>
                            {{ $rule }}
                        </li>
                    @endforeach
                </ul>

                @if ($notice)
                    <p class="mt-5 rounded-xl bg-sun/25 px-4 py-3 text-sm font-medium" role="status">
                        {{ $notice }}</p>
                @endif

                @if ($sub)
                    <button type="button" wire:click="startExam" wire:loading.attr="disabled"
                        wire:target="startExam" @disabled(!$examTypeId)
                        class="mt-6 w-full rounded-xl bg-linear-to-r from-brand to-brand-deep px-6 py-4 font-bold text-white shadow-[0_10px_30px_-10px_#7C5CFF] transition hover:shadow-[0_14px_36px_-8px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:opacity-60 sm:w-auto sm:px-10">
                        <span wire:loading.remove
                            wire:target="startExam">{{ __('portal.dashboard.exam.start', ['type' => $this->examTypes->firstWhere('id', $examTypeId)?->name]) }}</span>
                        <span wire:loading wire:target="startExam">{{ __('portal.dashboard.exam.starting') }}</span>
                    </button>
                @else
                    <div class="mt-6 flex flex-wrap items-center gap-4 rounded-2xl bg-lav p-5">
                        <svg viewBox="0 0 24 24" class="h-6 w-6 shrink-0 text-night/50" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $icons['lock'] }}" />
                        </svg>
                        <p class="flex-1 text-sm font-medium">{{ __('portal.dashboard.exam.locked') }}</p>
                        <a href="#plans"
                            class="rounded-xl bg-night px-5 py-2.5 text-sm font-bold text-white hover:bg-night-3">{{ __('portal.dashboard.exam.locked_cta') }}</a>
                    </div>
                @endif
            @endif
        </section>

        {{-- Where to focus --}}
        <section class="{{ $card }}" aria-labelledby="focus-title">
            <h2 id="focus-title" class="font-display text-lg font-bold">{{ __('portal.dashboard.focus.title') }}</h2>
            @if ($breakdown)
                <p class="mt-1 text-sm text-night/55">{{ __('portal.dashboard.focus.lead') }}</p>
                @foreach (['profile' => 'by_profile', 'area' => 'by_area'] as $group => $heading)
                    @if (!empty($breakdown[$group]))
                        <h3 class="mt-6 text-xs font-bold uppercase tracking-wide text-night/45">
                            {{ __('portal.dashboard.focus.' . $heading) }}</h3>
                        <div class="mt-3 space-y-4">
                            @foreach ($breakdown[$group] as $row)
                                @php $isWeak = $group === 'profile' && $weakest && $weakest['id'] === $row['id']; @endphp
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                                        <span class="font-semibold">
                                            {{ $row['name'] }}
                                            @if ($isWeak)
                                                <span
                                                    class="ms-1.5 rounded-full bg-coral/15 px-2 py-0.5 text-[11px] font-bold text-coral-deep">{{ __('portal.dashboard.focus.weakest') }}</span>
                                            @endif
                                        </span>
                                        <span class="font-bold tabular-nums"
                                            dir="ltr">{{ $row['percent'] }}%</span>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-lav-deep" role="img"
                                        aria-label="{{ $row['name'] }}: {{ $row['percent'] }}%">
                                        <div class="h-full rounded-full {{ $tone($row['percent']) }}"
                                            style="width: {{ min(100, max(0, $row['percent'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @else
                <p class="mt-3 text-sm leading-relaxed text-night/60">{{ __('portal.dashboard.focus.empty') }}</p>
            @endif
        </section>
    </div>

    {{-- ============ TREND + ATTEMPTS ============ --}}
    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <section class="{{ $card }}" aria-labelledby="trend-title">
            <h2 id="trend-title" class="font-display text-lg font-bold">{{ __('portal.dashboard.trend.title') }}</h2>
            <p class="mt-1 text-sm text-night/55">{{ __('portal.dashboard.trend.lead') }}</p>

            @if ($completed->isNotEmpty())
                <div class="mt-6 flex h-44 items-end gap-2.5" dir="ltr">
                    @foreach ($completed as $a)
                        <div class="flex h-full flex-1 flex-col justify-end gap-1.5 text-center"
                            title="{{ $a->examType?->name }} · {{ $a->completed_at->translatedFormat('j M') }}">
                            <span
                                class="text-[11px] font-bold tabular-nums">{{ number_format($a->scorePercent(), 0) }}</span>
                            <div class="w-full rounded-t-lg {{ $tone($a->scorePercent()) }}"
                                style="height: {{ max(6, min(100, $a->scorePercent())) * 0.78 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2.5 text-center text-[11px] text-night/50" dir="ltr">
                    @foreach ($completed as $a)
                        <span class="flex-1">{{ $a->completed_at->translatedFormat('j M') }}</span>
                    @endforeach
                </div>
            @else
                <p class="mt-6 rounded-2xl bg-lav p-5 text-sm leading-relaxed text-night/60">
                    {{ __('portal.dashboard.trend.empty') }}</p>
            @endif
        </section>

        <section class="{{ $card }} lg:col-span-2" aria-labelledby="attempts-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="attempts-title" class="font-display text-lg font-bold">
                    {{ __('portal.dashboard.attempts.title') }}</h2>
                <a href="{{ $link('attempts.index') }}"
                    class="text-sm font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.attempts.view_all') }}</a>
            </div>

            @if ($this->attempts->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-136 text-start text-sm">
                        <thead class="text-xs font-bold uppercase text-night/45">
                            <tr class="border-b border-lav-deep">
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.attempts.date') }}</th>
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.attempts.type') }}</th>
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.attempts.score') }}</th>
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.attempts.status') }}</th>
                                <th class="py-2.5 text-end font-bold"><span
                                        class="sr-only">{{ __('portal.dashboard.attempts.report') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-lav-deep/70">
                            @foreach ($this->attempts as $a)
                                <tr>
                                    <td class="py-3.5 pe-3 whitespace-nowrap">
                                        {{ $a->started_at->translatedFormat('j M Y') }}</td>
                                    <td class="py-3.5 pe-3 font-display font-bold">{{ $a->examType?->name }}</td>
                                    <td class="py-3.5 pe-3 font-bold tabular-nums" dir="ltr">
                                        {{ $a->scorePercent() !== null ? number_format($a->scorePercent(), 0) . '%' : '-' }}
                                    </td>
                                    <td class="py-3.5 pe-3"><span
                                            class="rounded-full px-2.5 py-1 text-xs font-bold {{ $badge($a->state()) }}">{{ $statusLabel('attempts', $a->state()) }}</span>
                                    </td>
                                    <td class="py-3.5 text-end">
                                        @if ($a->state() === 'completed')
                                            <a href="{{ $link('attempts.show', [$a]) }}"
                                                class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.attempts.report') }}</a>
                                        @elseif ($a->state() === 'in_progress')
                                            <a href="{{ $link('exams.take', [$a]) }}"
                                                class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.attempts.resume') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-4 rounded-2xl bg-lav p-6 text-center">
                    <p class="text-sm text-night/60">{{ __('portal.dashboard.attempts.empty') }}</p>
                    <a href="#start-exam"
                        class="mt-2 inline-block text-sm font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.attempts.empty_cta') }}</a>
                </div>
            @endif
        </section>
    </div>

    {{-- ============ PAYMENTS + HELP ============ --}}
    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <section class="{{ $card }} lg:col-span-2" aria-labelledby="payments-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="payments-title" class="font-display text-lg font-bold">
                    {{ __('portal.dashboard.payments.title') }}</h2>
                <a href="{{ $link('subscription.index') }}"
                    class="text-sm font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.payments.view_all') }}</a>
            </div>

            @if ($this->payments->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-136 text-start text-sm">
                        <thead class="text-xs font-bold uppercase text-night/45">
                            <tr class="border-b border-lav-deep">
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.payments.date') }}</th>
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.payments.plan') }}</th>
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.payments.amount') }}</th>
                                <th class="py-2.5 pe-3 text-start font-bold">
                                    {{ __('portal.dashboard.payments.status') }}</th>
                                <th class="py-2.5 text-end font-bold"><span
                                        class="sr-only">{{ __('portal.dashboard.payments.receipt') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-lav-deep/70">
                            @foreach ($this->payments as $p)
                                <tr>
                                    <td class="py-3.5 pe-3 whitespace-nowrap">
                                        {{ ($p->paid_at ?? $p->created_at)->translatedFormat('j M Y') }}</td>
                                    <td class="py-3.5 pe-3">{{ $p->subscription?->subscriptionPlan?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 pe-3 font-bold tabular-nums whitespace-nowrap" dir="ltr">
                                        {{ $num($p->amount) }} {{ $p->currencyCode() }}</td>
                                    <td class="py-3.5 pe-3"><span
                                            class="rounded-full px-2.5 py-1 text-xs font-bold {{ $badge($p->statusKey()) }}">{{ $statusLabel('payments', $p->statusKey()) }}</span>
                                    </td>
                                    <td class="py-3.5 text-end">
                                        @if ($p->statusKey() === 'paid')
                                            <a href="{{ $link('payments.receipt', [$p]) }}"
                                                class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('portal.dashboard.payments.receipt') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-4 rounded-2xl bg-lav p-6 text-center text-sm text-night/60">
                    {{ __('portal.dashboard.payments.empty') }}</p>
            @endif
        </section>

        <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-brand to-brand-deep p-7 text-white"
            aria-labelledby="help-title">
            <div class="pointer-events-none absolute -inset-e-10 -top-10 h-40 w-40 rounded-full bg-mint/30 blur-3xl"
                aria-hidden="true"></div>
            <h2 id="help-title" class="relative font-display text-xl font-bold">
                {{ __('portal.dashboard.help.title') }}</h2>
            <p class="relative mt-2 leading-relaxed text-white/80">{{ __('portal.dashboard.help.text') }}</p>
            <a href="{{ $link('contact') !== '#' ? $link('contact') : url('/contact') }}"
                class="relative mt-6 inline-block rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-night hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-deep">{{ __('portal.dashboard.help.cta') }}</a>
        </section>
    </div>
</div>
