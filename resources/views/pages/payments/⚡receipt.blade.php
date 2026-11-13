<?php

use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public Payment $payment;

    public function mount(Payment $payment): void
    {
        abort_unless($payment->subscription?->user_id === auth()->id(), 404);
        abort_unless($payment->statusKey() === 'paid', 404);

        $this->payment = $payment->load('subscription.subscriptionPlan');
    }
};
?>

<div class="mx-auto max-w-2xl">
@php
    $sub = $payment->subscription;
    $plan = $sub->subscriptionPlan;
    $user = auth()->user();
    $amount = number_format((float) $payment->amount, 2) . ' ' . $payment->currencyCode();
    $number = 'R-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <a href="{{ Route::has('subscription.index') ? route('subscription.index') : '#' }}" class="inline-flex items-center gap-2 text-sm font-bold text-brand-deep underline-offset-4 hover:underline">
        <svg viewBox="0 0 24 24" class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
        {{ __('payment.receipt.back') }}
    </a>
    <button type="button" onclick="window.print()" class="rounded-xl bg-night px-5 py-2.5 text-sm font-bold text-white hover:bg-night-3 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">{{ __('payment.receipt.print') }}</button>
</div>

<article class="rounded-3xl bg-white p-8 ring-1 ring-lav-deep sm:p-10 print:rounded-none print:p-0 print:ring-0">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-lav-deep pb-6">
        <div>
            <p class="font-display text-2xl font-extrabold">{{ config('app.name') }}</p>
            <p class="mt-1 text-sm text-night/60">{{ __('payment.receipt.heading') }}</p>
        </div>
        <div class="text-end">
            <p class="font-mono text-sm font-bold" dir="ltr">{{ $number }}</p>
            <p class="mt-1 inline-block rounded-full bg-mint/20 px-3 py-1 text-xs font-bold text-mint-deep">{{ \Illuminate\Support\Facades\Lang::has('payment.status.paid') ? __('payment.status.paid') : 'Paid' }}</p>
        </div>
    </header>

    <dl class="mt-6 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2">
        <div><dt class="text-night/55">{{ __('payment.receipt.billed_to') }}</dt><dd class="mt-0.5 font-bold">{{ $user->name }}</dd><dd class="text-night/60" dir="ltr">{{ $user->email }}</dd></div>
        <div><dt class="text-night/55">{{ __('payment.receipt.date') }}</dt><dd class="mt-0.5 font-bold">{{ $payment->paid_at?->translatedFormat('j M Y, H:i') }}</dd></div>
        @if ($payment->provider_payment_id)
            <div class="sm:col-span-2"><dt class="text-night/55">{{ __('payment.receipt.reference') }}</dt><dd class="mt-0.5 font-mono text-xs font-bold" dir="ltr">{{ $payment->provider_payment_id }}</dd></div>
        @endif
    </dl>

    <div class="mt-8 overflow-hidden rounded-2xl ring-1 ring-lav-deep">
        <table class="w-full text-start text-sm">
            <thead class="bg-lav text-xs font-bold uppercase text-night/50">
                <tr><th class="px-5 py-3 text-start">{{ __('payment.receipt.item') }}</th><th class="px-5 py-3 text-end">{{ __('payment.checkout.total') }}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="px-5 py-4">
                        <p class="font-bold">{{ $plan?->name }}</p>
                        <p class="mt-0.5 text-xs text-night/55">{{ __('payment.receipt.period') }}: {{ $sub->starts_at->translatedFormat('j M Y') }} &ndash; {{ $sub->ends_at->translatedFormat('j M Y') }}</p>
                    </td>
                    <td class="px-5 py-4 text-end font-bold tabular-nums" dir="ltr">{{ $amount }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="border-t border-lav-deep bg-lav/60">
                    <td class="px-5 py-4 font-display font-bold">{{ __('payment.receipt.total') }}</td>
                    <td class="px-5 py-4 text-end font-display text-xl font-extrabold tabular-nums" dir="ltr">{{ $amount }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="mt-6 text-xs text-night/50">{{ __('payment.receipt.note') }}</p>
</article>
</div>
