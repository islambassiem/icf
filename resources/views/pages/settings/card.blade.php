@props(['title', 'text' => null, 'danger' => false])

<section {{ $attributes->class([
    'rounded-3xl bg-white p-6 ring-1 sm:p-8',
    'ring-lav-deep' => ! $danger,
    'ring-coral/40' => $danger,
]) }}>
    <header class="mb-6">
        <h2 class="font-display text-xl font-extrabold tracking-tight {{ $danger ? 'text-coral-deep' : '' }}">{{ $title }}</h2>
        @if ($text)
            <p class="mt-1 max-w-xl text-sm leading-relaxed text-night/60">{{ $text }}</p>
        @endif
    </header>

    {{ $slot }}
</section>
