<section class="bg-lav" aria-label="{{ __('landing.stats_label') }}">
    <dl class="mx-auto grid max-w-7xl grid-cols-2 gap-px px-5 py-14 text-center md:grid-cols-4">
        @foreach (__('landing.stats') as $stat)
            <div class="py-4">
                <dd class="font-display text-6xl font-extrabold tracking-tight text-brand-deep sm:text-7xl">
                    {{ $stat['num'] ?? '' }}</dd>
                <dt class="mt-1 font-medium text-night/60">{{ $stat['label'] ?? '' }}</dt>
            </div>
        @endforeach
    </dl>
</section>
