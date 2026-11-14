<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::portal')] class extends Component {
    use WithPagination;

    /** The paid period running right now. */
    #[Computed]
    public function current()
    {
        return auth()->user()->activeSubscription()->with('subscriptionPlan')->first();
    }

    /** Paid periods that have not started yet (bought while another plan was running). */
    #[Computed]
    public function upcoming(): Collection
    {
        return auth()->user()->subscriptions()->with('subscriptionPlan')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get();
    }

    #[Computed]
    public function plans(): Collection
    {
        return SubscriptionPlan::active()->orderBy('duration_days')->get();
    }

    #[Computed]
    public function history()
    {
        return auth()->user()->payments()->with('subscription.subscriptionPlan')
            ->latest('payments.created_at')->paginate(10);
    }
};
?>

<div>
@php
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
    $current = $this->current;
    $num = fn ($n) => number_format((float) $n, fmod((float) $n, 1) ? 2 : 0);
    $badge = fn (string $s) => match ($s) {
        'paid' => 'bg-mint/20 text-mint-deep',
        'pending' => 'bg-sun/30 text-night',
        'failed' => 'bg-coral/15 text-coral-deep',
        default => 'bg-lav-deep text-night',
    };
    $statusLabel = fn (string $k) => \Illuminate\Support\Facades\Lang::has("payment.status.{$k}") ? __("payment.status.{$k}") : \Illuminate\Support\Str::headline($k);
    $card = 'rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-7';
@endphp

<h1 class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('payment.index.heading') }}</h1>
<p class="mt-1.5 text-night/65">{{ __('payment.index.lead') }}</p>

{{-- Current plan --}}
<section class="mt-8 grid gap-5 lg:grid-cols-3" aria-labelledby="cur-title">
    <div class="{{ $card }} lg:col-span-2">
        <h2 id="cur-title" class="font-display text-lg font-bold">{{ __('payment.index.current') }}</h2>
        @if ($current)
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="font-display text-3xl font-extrabold">{{ $current->subscriptionPlan?->name }}</p>
                    <p class="mt-1 text-sm text-night/60">{{ __('portal.dashboard.sub.valid_until', ['date' => $current->ends_at->translatedFormat('j M Y')]) }}</p>
                </div>
                <p class="font-display text-2xl font-extrabold">{{ trans_choice('portal.dashboard.sub.days_left', $current->daysLeft(), ['count' => $current->daysLeft()]) }}</p>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-lav-deep" role="presentation">
                <div class="h-full rounded-full bg-linear-to-r from-brand to-mint rtl:bg-linear-to-l" style="width: {{ $current->elapsedPercent() }}%"></div>
            </div>
        @else
            <p class="mt-4 rounded-2xl bg-lav p-5 text-night/65">{{ __('payment.index.none') }}</p>
        @endif

        @foreach ($this->upcoming as $next)
            <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-brand/8 px-4 py-3 text-sm">
                <span class="rounded-full bg-brand px-2.5 py-0.5 text-xs font-bold text-white">{{ __('payment.index.upcoming') }}</span>
                <span class="font-bold">{{ $next->subscriptionPlan?->name }}</span>
                <span class="text-night/60">{{ __('payment.index.upcoming_text', ['date' => $next->starts_at->translatedFormat('j M Y')]) }}</span>
            </p>
        @endforeach
    </div>

    {{-- Buy / add time --}}
    <div class="{{ $card }}">
        <h2 class="font-display text-lg font-bold">{{ $current ? __('payment.index.add') : __('payment.index.plans') }}</h2>
        <ul class="mt-4 space-y-3">
            @foreach ($this->plans as $plan)
                <li class="flex items-center justify-between gap-3 rounded-2xl bg-lav/70 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ $plan->name }}</p>
                        <p class="text-xs text-night/55" dir="ltr">{{ $num($plan->price) }} {{ $plan->currencyCode() }}</p>
                    </div>
                    <a href="{{ $link('subscription.checkout', ['plan' => $plan->id]) }}" class="shrink-0 rounded-lg bg-night px-3.5 py-2 text-xs font-bold text-white hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('payment.index.choose') }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- History --}}
<section class="{{ $card }} mt-5" aria-labelledby="hist-title">
    <h2 id="hist-title" class="font-display text-lg font-bold">{{ __('payment.index.history') }}</h2>

    @if ($this->history->isNotEmpty())
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-xl text-start text-sm">
                <thead class="text-xs font-bold uppercase text-night/45">
                    <tr class="border-b border-lav-deep">
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('payment.index.date') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('payment.index.plan') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('payment.index.amount') }}</th>
                        <th class="py-2.5 pe-3 text-start font-bold">{{ __('payment.index.status') }}</th>
                        <th class="py-2.5 text-end font-bold"><span class="sr-only">{{ __('payment.index.receipt') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-lav-deep/70">
                    @foreach ($this->history as $p)
                        @php
                            $key = $p->statusKey();
                            $why = $key === 'failed' && $p->failure_reason
                                ? (\Illuminate\Support\Facades\Lang::has("payment.return.reasons.{$p->failure_reason}") ? __("payment.return.reasons.{$p->failure_reason}") : null)
                                : null;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap py-3.5 pe-3">{{ ($p->paid_at ?? $p->created_at)->translatedFormat('j M Y') }}</td>
                            <td class="py-3.5 pe-3">{{ $p->subscription?->subscriptionPlan?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap py-3.5 pe-3 font-bold tabular-nums" dir="ltr">{{ $num($p->amount) }} {{ $p->currencyCode() }}</td>
                            <td class="py-3.5 pe-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $badge($key) }}">{{ $statusLabel($key) }}</span>
                                @if ($why)
                                    <span class="mt-1 block max-w-56 text-xs text-night/55">{{ $why }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 text-end">
                                @if ($key === 'paid')
                                    <a href="{{ $link('payments.receipt', [$p]) }}" class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('payment.index.receipt') }}</a>
                                @elseif ($key === 'failed' && $p->subscription)
                                    <a href="{{ $link('subscription.checkout', ['plan' => $p->subscription->subscription_plan_id]) }}" class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('payment.index.retry') }}</a>
                                @elseif ($key === 'pending')
                                    <a href="{{ $link('subscription.return', [$p]) }}" class="font-bold text-brand-deep underline-offset-4 hover:underline">{{ __('payment.return.title') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->history->links() }}</div>
    @else
        <p class="mt-4 rounded-2xl bg-lav p-6 text-center text-sm text-night/60">{{ __('payment.index.empty') }}</p>
    @endif
</section>
</div>
