<div class="space-y-5">
    @foreach ($scripts as $i => $script)
        @php
            $hashtags = implode(' ', array_map(fn ($tag) => '#' . $tag, $script['hashtags']));
            $plain = collect($script['scenes'])->map(fn ($scene, $n) => 'Scene ' . ($n + 1) . "\nVisual: {$scene['visual']}\nVO: {$scene['voiceover']}" . ($scene['text_overlay'] ? "\nTeks: {$scene['text_overlay']}" : ''))->implode("\n\n");
            $plain = "{$script['title']} ({$script['duration']} detik)\n\nHook: {$script['hook']}\n\n{$plain}\n\nCTA: {$script['cta']}\n\nCaption: {$script['caption']} {$hashtags}";
        @endphp
        <article class="rounded-lg border border-line">
            <div class="flex items-start justify-between gap-3 px-4 py-3 border-b border-line">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $script['title'] }}</p>
                    <p class="text-[13px] text-ink-soft">± {{ $script['duration'] }} detik · {{ count($script['scenes']) }} scene</p>
                </div>
                <button type="button" class="btn btn-sm btn-secondary shrink-0" @click="copy('script-{{ $i }}', @js($plain))">
                    <span x-text="copied === 'script-{{ $i }}' ? 'Tersalin' : 'Salin script'"></span>
                </button>
            </div>

            <div class="p-4 space-y-3 text-sm">
                @if ($script['hook'])
                    <p><span class="marker font-semibold">Hook</span> {{ $script['hook'] }}</p>
                @endif

                <ol class="space-y-3">
                    @foreach ($script['scenes'] as $n => $scene)
                        <li class="grid grid-cols-[2rem_1fr] gap-2">
                            <span class="size-7 rounded-full bg-racun-wash text-racun text-[12px] font-bold inline-flex items-center justify-center">{{ $n + 1 }}</span>
                            <div class="space-y-0.5">
                                @if ($scene['visual'])<p class="text-ink-soft"><span class="font-semibold text-ink">Visual:</span> {{ $scene['visual'] }}</p>@endif
                                @if ($scene['voiceover'])<p><span class="font-semibold">VO:</span> “{{ $scene['voiceover'] }}”</p>@endif
                                @if ($scene['text_overlay'])<p class="text-ink-soft"><span class="font-semibold text-ink">Teks layar:</span> {{ $scene['text_overlay'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($script['cta'])
                    <p><span class="font-semibold">CTA:</span> {{ $script['cta'] }}</p>
                @endif
                @if ($script['caption'] || $hashtags)
                    <p class="text-ink-soft border-t border-line pt-3">{{ $script['caption'] }} <span class="text-racun">{{ $hashtags }}</span></p>
                @endif
            </div>
        </article>
    @endforeach
</div>
