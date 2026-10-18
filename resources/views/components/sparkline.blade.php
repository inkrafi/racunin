@props(['values' => []])

@php
    $count = max(count($values), 1);
    $max = max(1, ...array_values($values ?: [0]));
    $barWidth = 5;
    $gap = 2;
    $height = 20;
    $width = $count * ($barWidth + $gap) - $gap;
@endphp

{{-- Daily clicks, oldest left, today right --}}
<svg {{ $attributes->merge(['class' => 'shrink-0']) }} width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" aria-hidden="true">
    @foreach (array_values($values) as $i => $value)
        @php $barHeight = $value > 0 ? max(3, round($value / $max * $height)) : 2; @endphp
        <rect x="{{ $i * ($barWidth + $gap) }}" y="{{ $height - $barHeight }}" width="{{ $barWidth }}" height="{{ $barHeight }}" rx="1"
              fill="{{ $value > 0 ? 'var(--color-racun)' : 'var(--color-line)' }}" />
    @endforeach
</svg>
