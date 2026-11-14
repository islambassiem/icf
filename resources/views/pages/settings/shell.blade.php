@props(['heading', 'lead' => null])
@php
    // [route, label key, active patterns]
    $tabs = [
        ['profile.edit', 'settings.nav.profile', ['profile.*']],
        ['security.edit', 'settings.nav.security', ['security.*']],
        ['appearance.edit', 'settings.nav.appearance', ['appearance.*']],
    ];
@endphp

<div class="mx-auto max-w-3xl">
    <h1 class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $heading }}</h1>
    @if ($lead)
        <p class="mt-1.5 text-night/65">{{ $lead }}</p>
    @endif

    {{-- Tabs --}}
    <nav class="-mx-1 mt-7 overflow-x-auto px-1 pb-1" aria-label="{{ __('settings.nav.label') }}">
        <ul class="inline-flex min-w-full gap-1.5 rounded-2xl bg-white p-1.5 ring-1 ring-lav-deep sm:min-w-0">
            @foreach ($tabs as [$route, $label, $patterns])
                @php $active = request()->routeIs(...$patterns); @endphp
                <li class="flex-1 sm:flex-none">
                    <a href="{{ Route::has($route) ? route($route) : '#' }}" @if ($active) aria-current="page" @endif
                       class="block whitespace-nowrap rounded-xl px-5 py-2.5 text-center text-sm font-bold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand
                              {{ $active ? 'bg-night text-white shadow-sm' : 'text-night/65 hover:bg-lav hover:text-night' }}">
                        {{ __($label) }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="mt-6 space-y-5">
        {{ $slot }}
    </div>
</div>
