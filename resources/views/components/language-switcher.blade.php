@php
    $target = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $label = $target === 'ar' ? '🇸🇦' : '🇺🇸';
@endphp

<a href="{{ route('locale.switch', $target) }}" hreflang="{{ $target }}" lang="{{ $target }}"
    aria-label="{{ __('landing.lang.switch') }}: {{ $label }}"
    {{ $attributes->class('') }}>
    {{-- inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-mint --}}
    {{-- <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
        stroke-linejoin="round" aria-hidden="true">
        <path d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 010 18M12 3a14 14 0 000 18" />
    </svg> --}}
    <span class="text-4xl">
        {{ $label }}
    </span>
</a>
