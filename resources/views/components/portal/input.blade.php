@props(['name', 'label', 'type' => 'text', 'value' => null])

@php
    $id = $attributes->get('id', $name);
    $isPassword = $type === 'password';
    $ltr = in_array($type, ['email', 'password'], true) || $attributes->has('inputmode');
@endphp

<div x-data="{ show: false }">
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-bold">{{ $label }}</label>
    <div class="relative">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($isPassword) type="password" x-bind:type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
            @unless ($isPassword) value="{{ old($name, $value) }}" @endunless
            @if ($ltr) dir="ltr" @endif
            @error($name) aria-invalid="true" @enderror
            {{ $attributes->except('id')->class([
                'block w-full rounded-xl border-2 bg-white px-4 py-3 text-base text-night placeholder:text-night/35 transition',
                'focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/15',
                'border-lav-deep' => ! $errors->has($name),
                'border-coral' => $errors->has($name),
                'pe-12' => $isPassword,
            ]) }}
        >
        @if ($isPassword)
            <button type="button" x-on:click="show = !show"
                    class="absolute inset-y-0 end-0 grid w-12 place-items-center rounded-e-xl text-night/45 hover:text-night focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
                    x-bind:aria-label="show ? @js(__('auth_ui.common.hide')) : @js(__('auth_ui.common.show'))">
                <svg x-show="!show" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.04 12.32a1 1 0 010-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" x-cloak viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.98 8.22A10.48 10.48 0 001.93 12c1.29 4.34 5.31 7.5 10.07 7.5 1.99 0 3.84-.55 5.42-1.51M6.23 6.23A10.45 10.45 0 0112 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 01-4.29 5.77M6.23 6.23L3 3m3.23 3.23l3.65 3.65m7.89 7.89L21 21m-3.23-3.23l-3.65-3.65m0 0a3 3 0 10-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
            </button>
        @endif
    </div>
    @error($name)
        <p class="mt-1.5 text-sm font-medium text-coral-deep">{{ $message }}</p>
    @enderror
</div>
