@if (! empty($ads['meta']))
    <div>
        <p class="text-[13px] font-semibold text-ink-soft">Meta Ads (Facebook &amp; Instagram)</p>
        <div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($ads['meta'] as $i => $ad)
                @php $full = trim($ad['primary_text'] . "\n\nHeadline: " . $ad['headline'] . "\nDeskripsi: " . $ad['description']); @endphp
                <div class="rounded-lg border border-line p-4 flex flex-col gap-3">
                    @if ($ad['angle'])
                        <span class="self-start text-[12px] font-semibold px-2 py-0.5 rounded-full bg-racun-wash text-racun">{{ $ad['angle'] }}</span>
                    @endif
                    <p class="flex-1 text-sm whitespace-pre-line leading-relaxed">{{ $ad['primary_text'] }}</p>
                    <div class="text-sm border-t border-line pt-3">
                        <p class="font-semibold">{{ $ad['headline'] }}</p>
                        <p class="text-ink-soft">{{ $ad['description'] }}</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary self-start" @click="copy('meta-{{ $i }}', @js($full))">
                        <span x-text="copied === 'meta-{{ $i }}' ? 'Tersalin' : 'Salin iklan'"></span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if (! empty($ads['tiktok']))
    <div class="mt-5">
        <p class="text-[13px] font-semibold text-ink-soft">TikTok Ads</p>
        <ul class="mt-2 divide-y divide-line rounded-lg border border-line">
            @foreach ($ads['tiktok'] as $i => $ad)
                <li class="flex items-start gap-3 p-4">
                    <div class="flex-1 min-w-0">
                        @if ($ad['angle'])
                            <p class="text-[12px] font-semibold text-racun">{{ $ad['angle'] }}</p>
                        @endif
                        <p class="text-sm">{{ $ad['text'] }}</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost shrink-0" @click="copy('tt-{{ $i }}', @js($ad['text']))">
                        <span x-text="copied === 'tt-{{ $i }}' ? 'Tersalin' : 'Salin'"></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<p class="mt-4 text-[13px] text-ink-soft">
    Tujuan iklan: pakai short link <span class="font-semibold text-ink">{{ $link->short_url }}</span>{{ $link->hasPublishedLandingPage() ? ' atau landing page-nya' : '' }} supaya klik dari iklan tercatat di Statistik.
</p>
