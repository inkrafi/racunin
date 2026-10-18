{{-- Expects: $discovery (ProductDiscovery) --}}
@php
    $scores = [
        ['label' => 'Keunikan', 'hint' => 'Seberapa jarang produk ini dipromosikan', 'value' => (int) $discovery->uniqueness_score],
        ['label' => 'Daya tarik emosional', 'hint' => 'Seberapa kuat memicu rasa ingin punya', 'value' => (int) $discovery->emotional_score],
    ];
@endphp

<div class="space-y-6" x-data="{ copied: false }">
    <div>
        <p class="text-sm text-ink-soft">Potensi viral</p>
        <p class="mt-0.5 text-2xl font-extrabold tracking-tight"><span class="marker">{{ $discovery->viral_potential }}</span></p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($scores as $score)
            <div>
                <div class="flex items-baseline justify-between">
                    <p class="text-sm font-semibold">{{ $score['label'] }}</p>
                    <p class="num"><span class="text-lg font-bold">{{ $score['value'] }}</span><span class="text-sm text-ink-soft">/100</span></p>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-paper overflow-hidden" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $score['value'] }}" aria-label="{{ $score['label'] }}">
                    <div class="h-full rounded-full bg-racun" style="width: {{ $score['value'] }}%"></div>
                </div>
                <p class="mt-1 text-[13px] text-ink-soft">{{ $score['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <dl class="space-y-5">
        <div>
            <dt class="text-sm font-semibold">Calon pembeli</dt>
            <dd class="mt-1 leading-relaxed max-w-prose">{{ $discovery->target_audience }}</dd>
        </div>
        <div>
            <dt class="text-sm font-semibold">Sudut promosi</dt>
            <dd class="mt-1 rounded-lg bg-racun-wash p-4">
                <p class="leading-relaxed max-w-prose">{{ $discovery->pitch_angle }}</p>
                <button type="button" class="btn btn-sm btn-secondary mt-3"
                        @click="navigator.clipboard.writeText(@js($discovery->pitch_angle)); copied = true; setTimeout(() => copied = false, 1800)">
                    <x-icon name="copy" /> <span x-text="copied ? 'Tersalin' : 'Salin sudut promosi'"></span>
                </button>
            </dd>
        </div>
        <div>
            <dt class="text-sm font-semibold">Alasan penilaian</dt>
            <dd class="mt-1 leading-relaxed text-ink-soft max-w-prose">{{ $discovery->reasoning }}</dd>
        </div>
    </dl>
</div>
