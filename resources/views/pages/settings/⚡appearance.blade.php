<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

// Language is the only appearance setting the portal has. The switch itself is the locale route,
// which saves the choice in the session and reloads the page in the new direction.
new #[Layout('layouts::portal')] class extends Component {};
?>

<div>
@php
    $current = app()->getLocale();
    $languages = [
        ['en', __('settings.appearance.english'), __('settings.appearance.english_hint'), 'Aa'],
        ['ar', __('settings.appearance.arabic'), __('settings.appearance.arabic_hint'), 'أب'],
    ];
@endphp

<x-settings.shell :heading="__('settings.appearance.heading')" :lead="__('settings.appearance.lead')">
    <x-settings.card :title="__('settings.appearance.card_title')" :text="__('settings.appearance.card_text')">
        <div class="grid gap-4 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('settings.appearance.card_title') }}">
            @foreach ($languages as [$code, $label, $hint, $glyph])
                @php $selected = $current === $code; @endphp
                <a href="{{ route('locale.switch', $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" role="radio" aria-checked="{{ $selected ? 'true' : 'false' }}"
                   class="group relative flex items-center gap-4 rounded-2xl border-2 p-5 transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand
                          {{ $selected ? 'border-brand bg-brand/8 shadow-lg shadow-brand/10' : 'border-lav-deep hover:border-brand/40' }}">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-xl font-display text-2xl font-extrabold {{ $selected ? 'bg-night text-mint' : 'bg-lav text-night' }}">{{ $glyph }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-display text-lg font-extrabold">{{ $label }}</span>
                        <span class="block text-sm text-night/55">{{ $hint }}</span>
                    </span>
                    @if ($selected)
                        <span class="rounded-full bg-brand px-2.5 py-1 text-xs font-bold text-white">{{ __('settings.appearance.current') }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </x-settings.card>
</x-settings.shell>
</div>
