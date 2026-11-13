<?php

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public Payment $payment;

    public int $polls = 0;

    public function mount(Payment $payment): void
    {
        abort_unless($payment->subscription?->user_id === auth()->id(), 404);

        $this->payment = $payment->load('subscription.subscriptionPlan');
    }

    /** Runs every 2 seconds while the payment is pending. */
    public function refresh(PaymentGateway $gateway, PaymentService $payments): void
    {
        $this->payment->refresh();

        if ($this->payment->statusKey() !== 'pending') {
            return;
        }

        $this->polls++;

        // The webhook is late: ask the provider directly (once a few polls have passed).
        if ($this->polls >= 3 && ($event = $gateway->fetchStatus($this->payment))) {
            $payments->applyEvent($event);
            $this->payment->refresh();
        }
    }

    /** paid | pending | failed */
    #[Computed]
    public function state(): string
    {
        return match ($this->payment->statusKey()) {
            'paid', 'refunded' => 'paid',
            'pending' => 'pending',
            default => 'failed',
        };
    }
};
?>

<div @if ($this->state === 'pending') wire:poll.2s="refresh" @endif>
@php
    $state = $this->state;
    $sub = $payment->subscription;
    $plan = $sub->subscriptionPlan;
    $slow = $state === 'pending' && $payment->created_at->diffInSeconds(now()) > 90;
    $queued = $state === 'paid' && $sub->starts_at->greaterThan($payment->paid_at?->addMinute() ?? now());
    $num = number_format((float) $payment->amount, fmod((float) $payment->amount, 1) ? 2 : 0) . ' ' . $payment->currencyCode();
    $reasonKey = (string) $payment->failure_reason;
    $reason = \Illuminate\Support\Facades\Lang::has("payment.return.reasons.{$reasonKey}")
        ? __("payment.return.reasons.{$reasonKey}")
        : ($reasonKey !== '' && ! preg_match('/^[a-z_]+$/', $reasonKey) ? $reasonKey : __('payment.return.reasons.unknown'));
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
@endphp

<div class="mx-auto max-w-xl">
    <div class="relative isolate overflow-hidden rounded-3xl bg-white p-8 text-center ring-1 ring-lav-deep sm:p-10">
        {{-- glow --}}
        <div class="pointer-events-none absolute inset-x-0 -top-24 -z-10 mx-auto h-56 w-72 rounded-full blur-[80px] {{ $state === 'paid' ? 'bg-mint/50' : ($state === 'failed' ? 'bg-coral/40' : 'bg-brand/30') }}" aria-hidden="true"></div>

        {{-- icon --}}
        <div class="mx-auto grid h-20 w-20 place-items-center rounded-full {{ $state === 'paid' ? 'bg-mint text-night' : ($state === 'failed' ? 'bg-coral text-white' : 'bg-lav text-brand-deep') }}">
            @if ($state === 'paid')
                <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            @elseif ($state === 'failed')
                <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            @else
                <svg viewBox="0 0 24 24" class="h-10 w-10 animate-spin" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a9 9 0 109 9" /></svg>
            @endif
        </div>

        {{-- text --}}
        @if ($state === 'paid')
            <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tight">{{ __('payment.return.paid.title') }}</h1>
            <p class="mt-2 text-night/65">
                {{ $queued ? __('payment.return.paid.text_queued', ['date' => $sub->starts_at->translatedFormat('j M Y')]) : __('payment.return.paid.text') }}
            </p>
        @elseif ($state === 'failed')
            <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tight">{{ __('payment.return.failed.title') }}</h1>
            <p class="mt-2 text-night/65">{{ __('payment.return.failed.text') }}</p>
            <p class="mx-auto mt-5 max-w-sm rounded-xl bg-coral/10 px-4 py-3 text-sm font-medium text-coral-deep ring-1 ring-coral/30" role="alert">
                <span class="font-bold">{{ __('payment.return.failed.reason') }}:</span> {{ $reason }}
            </p>
        @else
            <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tight">{{ __('payment.return.pending.title') }}</h1>
            <p class="mt-2 text-night/65">{{ __('payment.return.pending.text') }}</p>
            @if ($slow)
                <p class="mx-auto mt-5 max-w-sm rounded-xl bg-sun/25 px-4 py-3 text-sm leading-relaxed" role="status">{{ __('payment.return.slow.text') }}</p>
                <button type="button" wire:click="refresh" class="mt-4 text-sm font-bold text-brand-deep underline underline-offset-4">{{ __('payment.return.slow.check') }}</button>
            @endif
        @endif

        {{-- details --}}
        @if ($state === 'paid')
            <dl class="mt-8 divide-y divide-lav-deep rounded-2xl bg-lav/70 px-5 text-start text-sm">
                <div class="flex justify-between gap-4 py-3"><dt class="text-night/60">{{ __('payment.return.details.plan') }}</dt><dd class="font-bold">{{ $plan?->name }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-night/60">{{ __('payment.return.details.period') }}</dt><dd class="font-bold">{{ $sub->starts_at->translatedFormat('j M Y') }} &ndash; {{ $sub->ends_at->translatedFormat('j M Y') }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-night/60">{{ __('payment.return.details.amount') }}</dt><dd class="font-bold" dir="ltr">{{ $num }}</dd></div>
                @if ($payment->provider_payment_id)
                    <div class="flex justify-between gap-4 py-3"><dt class="text-night/60">{{ __('payment.return.details.reference') }}</dt><dd class="truncate font-mono text-xs font-bold" dir="ltr">{{ $payment->provider_payment_id }}</dd></div>
                @endif
            </dl>
        @elseif ($state === 'failed')
            <dl class="mt-8 divide-y divide-lav-deep rounded-2xl bg-lav/70 px-5 text-start text-sm">
                <div class="flex justify-between gap-4 py-3"><dt class="text-night/60">{{ __('payment.return.details.plan') }}</dt><dd class="font-bold">{{ $plan?->name }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-night/60">{{ __('payment.return.details.amount') }}</dt><dd class="font-bold" dir="ltr">{{ $num }}</dd></div>
            </dl>
        @endif

        {{-- actions --}}
        <div class="mt-8 flex flex-col gap-3">
            @if ($state === 'paid')
                <a href="{{ $link('exams.index') }}" class="rounded-xl bg-linear-to-r from-brand to-brand-deep px-6 py-3.5 font-bold text-white shadow-[0_10px_30px_-10px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('payment.return.paid.exam') }}</a>
                <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm font-bold">
                    <a href="{{ $link('payments.receipt', [$payment]) }}" class="text-brand-deep underline-offset-4 hover:underline">{{ __('payment.return.paid.receipt') }}</a>
                    <a href="{{ $link('dashboard') }}" class="text-brand-deep underline-offset-4 hover:underline">{{ __('payment.return.paid.dashboard') }}</a>
                </div>
            @elseif ($state === 'failed')
                <a href="{{ $link('subscription.checkout', ['plan' => $plan?->id]) }}" class="rounded-xl bg-night px-6 py-3.5 font-bold text-white hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('payment.return.failed.retry') }}</a>
                <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm font-bold">
                    <a href="{{ $link('dashboard') }}" class="text-brand-deep underline-offset-4 hover:underline">{{ __('payment.return.failed.dashboard') }}</a>
                    <a href="{{ url('/contact') }}" class="text-brand-deep underline-offset-4 hover:underline">{{ __('payment.return.failed.support') }}</a>
                </div>
            @endif
        </div>
    </div>
</div>
</div>
