<?php

use App\Models\SubscriptionPlan;
use App\Services\Payments\PaymentService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public SubscriptionPlan $plan;

    public bool $acceptTerms = false;

    public ?string $error = null;

    public function mount(SubscriptionPlan $plan): void
    {
        abort_unless($plan->is_active, 404);

        $this->plan = $plan;
    }

    public function pay(PaymentService $payments): void
    {
        $this->error = null;

        $this->validate(
            ['acceptTerms' => ['accepted']],
            ['acceptTerms.accepted' => __('payment.checkout.terms_required')],
        );

        try {
            [, $session] = $payments->startCheckout(auth()->user(), $this->plan);
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('payment.checkout.error');

            return;
        }

        // Hand over to the provider's hosted payment page.
        $this->redirect($session->url);
    }
};
?>

<div>
@php
    // $start = app(PaymentService::class)->nextStart(auth()->user());
    $start = now();
    $end = $start->addDays($plan->duration_days);
    $queued = $start->greaterThan(now()->addMinute());
    $price = number_format((float) $plan->price, fmod((float) $plan->price, 1) ? 2 : 0);
    $features = is_array($f = __('portal.dashboard.plans.features')) ? $f : [];
    $testMode = config('payments.driver') === 'fake';
@endphp

<a href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}" class="inline-flex items-center gap-2 text-sm font-bold text-brand-deep underline-offset-4 hover:underline">
    <svg viewBox="0 0 24 24" class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    {{ __('payment.checkout.back') }}
</a>

<h1 class="mt-4 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('payment.checkout.heading') }}</h1>
<p class="mt-1.5 max-w-2xl text-night/65">{{ __('payment.checkout.lead') }}</p>

@if ($testMode)
    <p class="mt-5 rounded-xl bg-sun/30 px-4 py-3 text-sm font-semibold ring-1 ring-sun/70" role="status">{{ __('payment.checkout.test_mode') }}</p>
@endif

<div class="mt-8 grid gap-6 lg:grid-cols-5">
    {{-- Left: details and consent --}}
    <section class="space-y-5 lg:col-span-3">
        <div class="rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-7">
            <h2 class="font-display text-lg font-bold">{{ __('payment.checkout.included') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($features as $feature)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-mint text-night"><svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></span>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-3xl bg-white p-6 ring-1 ring-lav-deep sm:p-7">
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" wire:model.live="acceptTerms" class="mt-1 h-5 w-5 rounded-md border-2 border-lav-deep accent-brand focus:ring-2 focus:ring-brand">
                <span class="text-sm leading-relaxed">
                    {{ __('payment.checkout.terms') }}
                    <a href="{{ url('/terms') }}" target="_blank" class="font-bold text-brand-deep underline underline-offset-4">{{ __('payment.checkout.terms_link') }}</a>
                </span>
            </label>
            @error('acceptTerms')
                <p class="mt-2 text-sm font-medium text-coral-deep">{{ $message }}</p>
            @enderror
        </div>
    </section>

    {{-- Right: order summary --}}
    <aside class="lg:col-span-2">
        <div class="relative isolate overflow-hidden rounded-3xl bg-night p-7 text-white lg:sticky lg:top-24">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="absolute -inset-e-16 -top-16 h-56 w-56 rounded-full bg-brand/50 blur-[80px]"></div>
                <div class="absolute -bottom-16 inset-s-0 h-48 w-48 rounded-full bg-mint/20 blur-[80px]"></div>
            </div>

            <h2 class="font-display text-lg font-bold">{{ __('payment.checkout.summary') }}</h2>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('payment.checkout.plan') }}</dt><dd class="font-bold">{{ $plan->name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('payment.checkout.duration') }}</dt><dd class="font-bold">{{ trans_choice('payment.checkout.days', $plan->duration_days, ['count' => $plan->duration_days]) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('payment.checkout.starts') }}</dt><dd class="font-bold">{{ $queued ? $start->translatedFormat('j M Y') : __('payment.checkout.starts_now') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('payment.checkout.ends') }}</dt><dd class="font-bold">{{ $end->translatedFormat('j M Y') }}</dd></div>
            </dl>

            @if ($queued)
                <p class="mt-4 rounded-xl bg-white/10 px-3.5 py-2.5 text-xs leading-relaxed text-white/80">{{ __('payment.checkout.queued') }}</p>
            @endif

            <div class="mt-6 flex items-end justify-between gap-4 border-t border-white/15 pt-5">
                <span class="text-white/65">{{ __('payment.checkout.total') }}</span>
                <span class="flex items-baseline gap-1.5" dir="ltr">
                    <span class="font-display text-4xl font-extrabold tabular-nums">{{ $price }}</span>
                    <span class="text-sm font-bold text-white/65">{{ $plan->currencyCode() }}</span>
                </span>
            </div>

            @if ($error)
                <p class="mt-5 rounded-xl bg-coral/20 px-3.5 py-3 text-sm font-medium ring-1 ring-coral/50" role="alert">{{ $error }}</p>
            @endif

            <button type="button" wire:click="pay" wire:loading.attr="disabled" wire:target="pay"
                class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-white px-6 py-4 font-bold text-night transition hover:bg-mint focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-night disabled:opacity-70">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <span wire:loading.remove wire:target="pay">{{ __('payment.checkout.pay') }}</span>
                <span wire:loading wire:target="pay">{{ __('payment.checkout.paying') }}</span>
            </button>

            <p class="mt-4 text-center text-xs leading-relaxed text-white/55">{{ __('payment.checkout.secure') }}</p>
        </div>
    </aside>
</div>
</div>
