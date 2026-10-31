@props(['title' => null, 'heading' => null, 'lead' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
    class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }} | {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@500;700;800&family=DM+Sans:wght@400;500;700&family=IBM+Plex+Sans+Arabic:wght@400;600&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-lav font-body text-night antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1fr_1.05fr]">

        {{-- Brand panel (desktop) --}}
        <aside
            class="relative isolate hidden flex-col justify-between overflow-hidden bg-night p-12 text-white lg:flex">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="bg-grid absolute inset-0"></div>
                <div class="absolute -start-24 top-10 h-96 w-96 animate-blob rounded-full bg-brand/50 blur-[100px]">
                </div>
                <div
                    class="absolute -end-20 bottom-24 h-80 w-80 animate-blob rounded-full bg-mint/25 blur-[100px] [animation-delay:-7s]">
                </div>
                <div
                    class="absolute bottom-0 start-1/3 h-64 w-64 animate-blob rounded-full bg-coral/25 blur-[100px] [animation-delay:-12s]">
                </div>
            </div>

            <a href="{{ url('/') }}" class="flex items-center gap-2 font-display text-xl font-extrabold">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-linear-to-br from-brand to-mint text-night">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                {{ config('app.name') }}
            </a>

            <div>
                <h2 class="font-display text-5xl font-extrabold leading-[1.1] tracking-tight xl:text-6xl">
                    {{ __('auth_ui.panel.title_1') }}
                    <span class="text-gradient block">{{ __('auth_ui.panel.title_2') }}</span>
                </h2>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-white/70">{{ __('auth_ui.panel.lead') }}</p>

                <ul class="mt-8 space-y-3">
                    @foreach (__('auth_ui.panel.points') as $point)
                        <li class="flex items-center gap-3 text-white/85">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-mint text-night"><svg
                                    viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                    stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 13l4 4L19 7" />
                                </svg></span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <dl class="glass grid grid-cols-3 divide-x divide-white/10 rounded-2xl text-center rtl:divide-x-reverse">
                @foreach (__('auth_ui.panel.facts') as $fact)
                    <div class="px-3 py-5">
                        <dd class="font-display text-3xl font-extrabold">{{ $fact['num'] }}</dd>
                        <dt class="mt-0.5 text-xs text-white/65">{{ $fact['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </aside>

        {{-- Form panel --}}
        <main class="flex min-h-screen flex-col">
            <div class="flex items-center justify-between p-5">
                <a href="{{ url('/') }}"
                    class="flex items-center gap-2 font-display text-lg font-extrabold lg:invisible">
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-linear-to-br from-brand to-mint text-night">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                    {{ config('app.name') }}
                </a>
                <x-language-switcher />
            </div>

            <div class="flex flex-1 items-center justify-center px-5 pb-12">
                <div
                    class="w-full max-w-md rounded-3xl bg-white p-7 shadow-xl shadow-brand/10 ring-1 ring-lav-deep sm:p-9">
                    @if ($heading)
                        <h1 class="font-display text-3xl font-extrabold tracking-tight">{{ $heading }}</h1>
                    @endif
                    @if ($lead)
                        <p class="mt-2 leading-relaxed text-night/65">{{ $lead }}</p>
                    @endif

                    <div class="mt-7">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </main>
    </div>
    @livewireScripts
</body>

</html>
