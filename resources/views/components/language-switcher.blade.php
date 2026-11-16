@php
    $target = app()->getLocale() === 'ar' ? 'en' : 'ar';

    $flag = $target === 'ar' ? 'sa' : 'us';
    $label = $target === 'ar' ? 'العربية' : 'English';
@endphp

<a href="{{ route('locale.switch', $target) }}" hreflang="{{ $target }}" lang="{{ $target }}"
    aria-label="{{ __('landing.lang.switch') }}: {{ $label }}" {{ $attributes->class('') }}>
    <img src="{{ asset("images/flags/{$flag}.svg") }}" alt="{{ $label }}" width="40"
        class="w-10 h-auto object-contain">
</a>
