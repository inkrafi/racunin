@props(['href' => '/', 'dark' => false])

{{-- Wordmark: the slash is the path separator of every short link --}}
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'font-display inline-flex items-baseline text-[22px] font-extrabold tracking-[-0.04em] ' . ($dark ? 'text-white' : 'text-ink')]) }} aria-label="Racunin">
    <span>racunin</span><span @class(['text-lime' => $dark, 'text-racun' => ! $dark]) aria-hidden="true">/</span>
</a>
