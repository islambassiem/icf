<button {{ $attributes->merge(['type' => 'submit'])->class('w-full rounded-xl bg-linear-to-r from-brand to-brand-deep px-6 py-3.5 font-bold text-white shadow-[0_10px_30px_-10px_#7C5CFF] transition hover:shadow-[0_14px_36px_-8px_#7C5CFF] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:opacity-50') }}>
    {{ $slot }}
</button>
