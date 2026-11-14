@php
    $target = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $label = $target === 'ar' ? '🇸🇦' : '🇺🇸';
@endphp

<a href="{{ route('locale.switch', $target) }}" hreflang="{{ $target }}" lang="{{ $target }}"
    aria-label="{{ __('landing.lang.switch') }}: {{ $label }}"
    {{ $attributes->class('') }}>
    <span class="text-4xl">
        {{ $label }}
    </span>
</a>
