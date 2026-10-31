<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::landing')] class extends Component {
    public string $level = 'ACC';

    public function setLevel(string $level): void
    {
        if (in_array($level, ['ACC', 'PCC'], true)) {
            $this->level = $level;
        }
    }

    /** Texts come from lang/{locale}/landing.php; the best/worst positions are the same in every language. */
    #[Computed]
    public function sample(): array
    {
        $texts = __('landing.sample.' . strtolower($this->level));

        return (is_array($texts) ? $texts : []) + [
            'area' => '',
            'profile' => '',
            'question' => '',
            'options' => [],
            'best_note' => '',
            'worst_note' => '',
            'best' => 0,
            'worst' => 3,
        ];
    }
};
?>

<div class="overflow-x-clip">
    @php
        $list = fn(string $key): array => is_array($v = __($key)) ? $v : [];
    @endphp
    x
    <x-landing.nav />
    <x-landing.hero :level="$level" :sample="$this->sample" :list="$list"/>
    <x-landing.marquee />
    <x-landing.stats />
    <x-landing.mock :list="$list"/>
    <x-landing.why-us :list="$list"/>
    <x-landing.report :list="$list"/>
    <x-landing.how-it-works :list="$list"/>
    <x-landing.faq :list="$list"/>
    <x-landing.final-cta />
    <x-landing.footer />
</div>
