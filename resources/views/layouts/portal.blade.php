@props(['title' => null])
@php
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : '#';
    $user = auth()->user();
    $initials = $user->initials();

    $icons = [
        'home' => 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'play' => 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z',
        'list' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
        'card' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
        'user' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
        'logout' => 'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75',
        'menu' => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
        'close' => 'M6 18L18 6M6 6l12 12',
    ];

    // [route name, label key, icon, active pattern]
    $items = [
        ['dashboard', 'nav.dashboard', 'home', 'dashboard'],
        ['exams.index', 'nav.take_exam', 'play', 'exams.*'],
        ['attempts.index', 'nav.attempts', 'list', 'attempts.*'],
        ['subscription.index', 'nav.subscription', 'card', 'subscription.*'],
        ['profile.edit', 'nav.profile', 'user', 'profile.*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('portal.dashboard.title') }} | {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;700;800&family=DM+Sans:wght@400;500;700&family=IBM+Plex+Sans+Arabic:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-lav font-body text-night antialiased" x-data="{ open: false }" x-on:keydown.escape.window="open = false">

    {{-- Mobile overlay --}}
    <div x-show="open" x-cloak x-transition.opacity x-on:click="open = false" class="fixed inset-0 z-30 bg-night/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    {{-- Sidebar (drawer on mobile) --}}
    <aside id="sidebar"
           class="print:hidden fixed inset-y-0 start-0 z-40 flex w-72 -translate-x-full flex-col overflow-hidden bg-night text-white transition-transform duration-300 rtl:translate-x-full lg:translate-x-0 lg:rtl:translate-x-0"
           x-bind:class="{ 'translate-x-0! rtl:translate-x-0!': open }">
        <div class="pointer-events-none absolute inset-0 -z-0" aria-hidden="true">
            <div class="absolute -start-20 -top-10 h-64 w-64 rounded-full bg-brand/40 blur-[90px]"></div>
            <div class="absolute -bottom-10 -end-20 h-64 w-64 rounded-full bg-mint/15 blur-[90px]"></div>
        </div>

        <div class="relative flex items-center justify-between px-6 pt-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-display text-xl font-extrabold">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-linear-to-br from-brand to-mint text-night">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                {{ config('app.name') }}
            </a>
            <button type="button" x-on:click="open = false" class="rounded-lg p-1.5 text-white/70 hover:bg-white/10 hover:text-white lg:hidden" aria-label="{{ __('portal.nav.close') }}">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['close'] }}"/></svg>
            </button>
        </div>

        <nav class="relative mt-10 flex-1 space-y-1 overflow-y-auto px-4" aria-label="{{ __('portal.nav.main') }}">
            @foreach ($items as [$route, $label, $icon, $pattern])
                @php $active = request()->routeIs($pattern); @endphp
                <a href="{{ $link($route) }}" @if ($active) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-[15px] font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint
                          {{ $active ? 'bg-white/12 text-white shadow-inner ring-1 ring-white/15' : 'text-white/65 hover:bg-white/8 hover:text-white' }}">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 {{ $active ? 'text-mint' : '' }}" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$icon] }}"/></svg>
                    {{ __('portal.' . $label) }}
                </a>
            @endforeach
        </nav>

        {{-- User --}}
        <div class="relative border-t border-white/10 p-4">
            <div class="flex items-center gap-3 px-2 pb-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-linear-to-br from-brand to-coral font-display text-sm font-extrabold">{{ $initials }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ $user->name }}</p>
                    <p class="truncate text-xs text-white/55" dir="ltr">{{ $user->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold text-white/65 transition hover:bg-white/8 hover:text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['logout'] }}"/></svg>
                    {{ __('portal.nav.logout') }}
                </button>
            </form>
        </div>
    </aside>

    {{-- Content --}}
    <div class="lg:ps-72 print:ps-0">
        <header class="print:hidden sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-lav-deep bg-lav/85 px-5 py-3 backdrop-blur lg:px-10">
            <button type="button" x-on:click="open = true" class="rounded-lg p-2 text-night hover:bg-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand lg:hidden" aria-controls="sidebar" aria-label="{{ __('portal.nav.menu') }}">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['menu'] }}"/></svg>
            </button>
            <span class="hidden lg:block"></span>
            <x-language-switcher class="bg-white text-night ring-1 ring-lav-deep hover:ring-brand" />
        </header>

        <main class="mx-auto max-w-6xl px-5 py-8 lg:px-10 lg:py-10">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
