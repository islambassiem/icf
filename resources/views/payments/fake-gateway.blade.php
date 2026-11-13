<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test payment page</title>
    @vite(['resources/css/app.css'])
</head>
@php
    $plan = $payment->subscription->subscriptionPlan;
    $amount = number_format((float) $payment->amount, 2) . ' ' . $payment->currencyCode();
@endphp

<body class="min-h-screen bg-zinc-100 font-sans text-zinc-900 antialiased">
    <div class="bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-zinc-900">
        TEST MODE: this is a simulated payment page. No money is charged.
    </div>

    <main class="mx-auto flex max-w-md flex-col px-5 py-10">
        <p class="text-center text-xs font-semibold uppercase tracking-widest text-zinc-500">Simulated payment provider
        </p>

        <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-zinc-500">Pay {{ config('app.name') }}</p>
            <p class="mt-1 text-4xl font-bold tabular-nums">{{ $amount }}</p>
            <p class="mt-1 text-sm text-zinc-600">{{ $plan->name_en }} &middot; {{ $plan->duration_days }} days</p>

            <div class="mt-6 rounded-xl bg-zinc-50 p-4 text-sm text-zinc-600">
                <p class="font-semibold text-zinc-800">Choose what should happen</p>
                <p class="mt-1">Each button sends a signed webhook to the portal, like a real provider does, then
                    returns you to the portal.</p>
            </div>

            <form method="POST" action="{{ route('fake-gateway.complete', $payment) }}" class="mt-6 space-y-3">
                @csrf
                <button name="outcome" value="pay"
                    class="w-full rounded-xl bg-emerald-600 px-5 py-3.5 font-bold text-white hover:bg-emerald-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Pay
                    successfully</button>
                <button name="outcome" value="declined"
                    class="w-full rounded-xl bg-white px-5 py-3 font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">Simulate:
                    card declined</button>
                <button name="outcome" value="insufficient_funds"
                    class="w-full rounded-xl bg-white px-5 py-3 font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">Simulate:
                    insufficient funds</button>
                <button name="outcome" value="canceled"
                    class="w-full rounded-xl px-5 py-3 font-semibold text-zinc-600 hover:bg-zinc-100">Cancel and go
                    back</button>
            </form>
        </div>
    </main>
</body>

</html>
