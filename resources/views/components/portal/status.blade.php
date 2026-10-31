@props(['status' => null])

@if ($status)
    <div {{ $attributes->class('mb-6 rounded-xl bg-mint/15 px-4 py-3 text-sm font-medium text-night ring-1 ring-mint-deep/40') }} role="status">
        {{ $status }}
    </div>
@endif
