<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-6">
    @php
        $channelNames = [
            'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'youtube' => 'YouTube',
            'facebook' => 'Facebook', 'twitter' => 'X', 'telegram' => 'Telegram', 'threads' => 'Threads',
            'search' => 'Mesin pencari', 'direct' => 'Langsung', 'other' => 'Lainnya',
        ];
        $channelName = fn ($key) => $channelNames[$key] ?? ucfirst($key);
        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');

        $peak = collect($trends)->sortByDesc('clicks')->first();
        $hasTraffic = $metrics['total_clicks'] > 0;
        $selectedLink = $linkId ? $userLinks->firstWhere('id', $linkId) : null;
    @endphp

    {{-- ===== Header & filters ===== --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <h1 class="text-2xl sm:text-[28px] font-extrabold tracking-tight">Statistik</h1>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <label class="sr-only" for="link-filter">Pilih link</label>
            <select id="link-filter" wire:change="selectLink($event.target.value)" class="field sm:w-72">
                <option value="" {{ $linkId === null ? 'selected' : '' }}>Semua link</option>
                @foreach ($userLinks as $l)
                    <option value="{{ $l->id }}" {{ $linkId == $l->id ? 'selected' : '' }}>{{ Str::limit($l->title, 42) }}</option>
                @endforeach
            </select>
            <div class="segmented self-start" role="group" aria-label="Rentang waktu">
                @foreach ([7, 30, 90] as $range)
                    <button type="button" wire:click="setDays({{ $range }})" aria-pressed="{{ $days === $range ? 'true' : 'false' }}">{{ $range }} hari</button>
                @endforeach
            </div>
        </div>
    </div>

    <div wire:loading.delay wire:target="selectLink,setDays" class="space-y-4">
        <div class="skeleton h-24"></div>
        <div class="skeleton h-64"></div>
    </div>

    <div wire:loading.remove.delay wire:target="selectLink,setDays" class="space-y-6">

        {{-- ===== Summary ===== --}}
        <section class="panel p-5 sm:p-6">
            <p class="text-ink-soft">{{ $selectedLink ? Str::limit($selectedLink->title, 60) : 'Semua link' }}, {{ $days }} hari terakhir</p>
            <p class="mt-1 text-4xl sm:text-5xl font-extrabold tracking-tight num">{{ $fmt($metrics['total_clicks']) }} <span class="text-2xl sm:text-3xl font-bold text-ink-soft">klik</span></p>

            <dl class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-0 sm:divide-x divide-line">
                <div class="sm:pr-6">
                    <dt class="text-sm text-ink-soft">Pengunjung unik</dt>
                    <dd class="mt-0.5 text-xl font-bold num">{{ $fmt($metrics['unique_visitors']) }}</dd>
                    <dd class="text-[13px] text-ink-soft">Dihitung per hari, tanpa bot</dd>
                </div>
                <div class="sm:px-6">
                    <dt class="text-sm text-ink-soft">Dari HP</dt>
                    <dd class="mt-0.5 text-xl font-bold num">{{ str_replace('.', ',', $devices['mobile']['percentage']) }}%</dd>
                    <dd class="text-[13px] text-ink-soft">{{ $fmt($metrics['mobile_clicks']) }} klik</dd>
                </div>
                <div class="sm:pl-6">
                    <dt class="text-sm text-ink-soft">Sumber terbanyak</dt>
                    <dd class="mt-0.5 text-xl font-bold">{{ $hasTraffic ? $channelName($metrics['top_channel']) : '–' }}</dd>
                    <dd class="text-[13px] text-ink-soft">{{ $hasTraffic && isset($channels[0]) ? str_replace('.', ',', $channels[0]['percentage']) . '% dari semua klik' : 'Belum ada klik' }}</dd>
                </div>
            </dl>
        </section>

        {{-- ===== Daily chart ===== --}}
        <section class="panel p-5 sm:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-bold">Klik per hari</h2>
                @if ($hasTraffic && $peak)
                    <p class="text-sm text-ink-soft">
                        Hari terbaik: {{ \Carbon\Carbon::parse($peak['date'])->translatedFormat('l, j M') }},
                        <span class="marker font-semibold text-ink num">{{ $fmt($peak['clicks']) }} klik</span>
                    </p>
                @endif
            </div>

            @if ($hasTraffic)
                <div class="mt-6 flex items-end gap-[3px] sm:gap-1 h-48" role="img" aria-label="Grafik klik per hari selama {{ $days }} hari terakhir">
                    @foreach ($trends as $index => $trend)
                        @php
                            $heightPct = $trend['clicks'] > 0 ? max(2, round($trend['clicks'] / $maxDailyClicks * 100)) : 0;
                            $date = \Carbon\Carbon::parse($trend['date']);
                        @endphp
                        <div class="group relative flex-1 h-full flex items-end">
                            <div class="w-full rounded-t-[3px] {{ $trend['clicks'] > 0 ? 'bg-racun group-hover:bg-racun-deep' : 'bg-line' }}"
                                 style="height: {{ $trend['clicks'] > 0 ? $heightPct : 1.5 }}%"></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block whitespace-nowrap rounded-md bg-ink px-2 py-1 text-xs text-white z-10">
                                {{ $date->translatedFormat('D, j M') }}: <span class="font-semibold num">{{ $fmt($trend['clicks']) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-xs text-ink-soft num">
                    <span>{{ \Carbon\Carbon::parse($trends->first()['date'])->translatedFormat('j M') }}</span>
                    <span>Hari ini</span>
                </div>
            @else
                <div class="mt-4 py-10 text-center">
                    <p class="font-semibold">Belum ada klik di rentang ini</p>
                    <p class="mt-1 text-sm text-ink-soft">Bagikan short link kamu, klik akan muncul di sini dalam hitungan detik.</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary mt-4">Lihat link kamu</a>
                </div>
            @endif
        </section>

        {{-- ===== AI advice ===== --}}
        @php $canRefresh = ! $aiInsightModel || $aiInsightModel->created_at->diffInHours(now()) >= 24; @endphp
        <section class="panel p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-bold">Saran dari AI</h2>
                    <p class="text-sm text-ink-soft">
                        @if ($aiInsightModel)
                            Dibuat {{ $aiInsightModel->created_at->diffForHumans() }} dari data klik kamu.
                        @else
                            AI membaca data klik kamu dan memberi saran kapan dan di mana sebaiknya posting.
                        @endif
                    </p>
                </div>
                @if ($canRefresh)
                    <button type="button" wire:click="generateAiInsight" wire:loading.attr="disabled" wire:target="generateAiInsight" class="btn btn-secondary shrink-0 self-start">
                        <span wire:loading.remove wire:target="generateAiInsight" class="inline-flex items-center gap-2"><x-icon name="sparkle" /> {{ $aiInsight ? 'Analisis ulang' : 'Analisis sekarang' }}</span>
                        <span wire:loading wire:target="generateAiInsight">Menganalisis…</span>
                    </button>
                @endif
            </div>

            @if ($aiInsightError)
                <div class="mt-4 flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-alert-wash text-alert text-sm">
                    <x-icon name="alert" class="mt-0.5" /> <p>{{ $aiInsightError }}</p>
                </div>
            @endif

            @if ($aiInsight)
                <div class="mt-5 grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div class="space-y-4">
                        <p class="text-[15px] leading-relaxed max-w-prose">{{ $aiInsight['executive_summary'] }}</p>
                        <ul class="space-y-2.5 max-w-prose">
                            @foreach ($aiInsight['top_insights'] as $insight)
                                <li class="flex gap-3 text-sm leading-relaxed">
                                    <span class="mt-2 size-1.5 rounded-full bg-racun shrink-0"></span>
                                    <span>{{ $insight }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="space-y-3">
                        <div class="rounded-lg bg-racun-wash p-4">
                            <p class="text-sm font-semibold text-racun">Lakukan hari ini</p>
                            <p class="mt-1 text-sm leading-relaxed">{{ $aiInsight['recommended_action'] }}</p>
                        </div>
                        <div class="rounded-lg border border-line p-4">
                            <p class="text-sm font-semibold flex items-center gap-2"><x-icon name="clock" class="text-ink-soft" /> Jam posting</p>
                            <p class="mt-1 text-sm leading-relaxed text-ink-soft">{{ $aiInsight['best_posting_times'] }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- ===== Sources & devices ===== --}}
        <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
            <section class="panel p-5 sm:p-6">
                <h2 class="font-bold">Sumber klik</h2>
                <p class="text-sm text-ink-soft">Dari mana orang membuka short link kamu.</p>
                @if (count($channels))
                    <ul class="mt-5 space-y-3.5">
                        @foreach ($channels as $data)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-semibold">{{ $channelName($data['channel']) }}</span>
                                    <span class="text-ink-soft num"><span class="text-ink font-semibold">{{ $fmt($data['count']) }}</span> ({{ str_replace('.', ',', $data['percentage']) }}%)</span>
                                </div>
                                <div class="mt-1.5 h-2 rounded-full bg-paper overflow-hidden">
                                    <div class="h-full rounded-full bg-racun" style="width: {{ $data['percentage'] }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if (collect($channels)->contains('channel', 'direct'))
                        <p class="mt-5 text-[13px] text-ink-soft">"Langsung" berarti link dibuka tanpa info asal, misalnya dari chat pribadi atau disalin manual.</p>
                    @endif
                @else
                    <p class="mt-5 text-sm text-ink-soft">Belum ada data sumber.</p>
                @endif
            </section>

            <section class="panel p-5 sm:p-6">
                <h2 class="font-bold">Perangkat</h2>
                <p class="text-sm text-ink-soft">Jenis perangkat pengunjung.</p>
                @php
                    $deviceNames = ['mobile' => 'HP', 'desktop' => 'Komputer', 'tablet' => 'Tablet'];
                    $deviceColors = ['mobile' => 'bg-racun', 'desktop' => 'bg-ink-soft', 'tablet' => 'bg-line-strong'];
                @endphp
                @if ($hasTraffic)
                    <div class="mt-5 flex h-3 rounded-full overflow-hidden bg-paper" aria-hidden="true">
                        @foreach ($devices as $device => $data)
                            <div class="{{ $deviceColors[$device] ?? 'bg-line' }}" style="width: {{ $data['percentage'] }}%"></div>
                        @endforeach
                    </div>
                    <ul class="mt-4 space-y-2.5">
                        @foreach ($devices as $device => $data)
                            <li class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm {{ $deviceColors[$device] ?? 'bg-line' }}"></span>{{ $deviceNames[$device] ?? ucfirst($device) }}</span>
                                <span class="num text-ink-soft"><span class="text-ink font-semibold">{{ str_replace('.', ',', $data['percentage']) }}%</span> ({{ $fmt($data['count']) }})</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 text-sm text-ink-soft">Belum ada data perangkat.</p>
                @endif
            </section>
        </div>

        {{-- ===== Top links (a ranking, so numbered) ===== --}}
        <section class="panel overflow-hidden">
            <div class="px-5 sm:px-6 pt-5 sm:pt-6 pb-3">
                <h2 class="font-bold">Link teratas</h2>
                <p class="text-sm text-ink-soft">Diurutkan dari klik terbanyak dalam {{ $days }} hari.</p>
            </div>
            @if ($topLinks->isNotEmpty())
                <table class="w-full text-sm">
                    <thead class="sr-only sm:not-sr-only">
                        <tr class="border-y border-line text-left text-[13px] text-ink-soft">
                            <th class="pl-5 sm:pl-6 pr-2 py-2 font-medium w-10">#</th>
                            <th class="px-2 py-2 font-medium">Produk</th>
                            <th class="px-2 py-2 font-medium text-right">Klik</th>
                            <th class="pl-2 pr-5 sm:pr-6 py-2 font-medium text-right hidden sm:table-cell">Pengunjung unik</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($topLinks as $index => $link)
                            <tr>
                                <td class="pl-5 sm:pl-6 pr-2 py-3 text-ink-soft num">{{ $index + 1 }}</td>
                                <td class="px-2 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($link->og_image_url)
                                            <img src="{{ $link->og_image_url }}" alt="" loading="lazy" class="size-9 rounded-md object-cover border border-line shrink-0">
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-semibold truncate">{{ $link->title }}</p>
                                            <p class="text-[13px] text-ink-soft truncate">/{{ $link->slug }}@if ($isAdmin && $link->user)<span class="ml-2">oleh {{ $link->user->name }}</span>@endif</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-right font-semibold num">{{ $fmt($link->period_clicks ?? 0) }}</td>
                                <td class="pl-2 pr-5 sm:pr-6 py-3 text-right text-ink-soft num hidden sm:table-cell">{{ $fmt($link->unique_clicks ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="px-5 sm:px-6 pb-6 text-sm text-ink-soft">Belum ada link.</p>
            @endif
        </section>
    </div>
</div>
