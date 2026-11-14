@props(['on'])

{{-- Shows "Saved" for a moment after the Livewire event named in `on` fires. --}}
<span x-data="{ show: false }" x-on:{{ $on }}.window="show = true; setTimeout(() => show = false, 2500)"
      x-show="show" x-cloak x-transition.opacity role="status"
      class="inline-flex items-center gap-1.5 text-sm font-bold text-mint-deep">
    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    {{ __('settings.saved') }}
</span>
