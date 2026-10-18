<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-6">
    @php
        $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
        $earned = $summary['completed'] + $summary['pending'];
        $reviewing = $headers !== [];
    @endphp

    {{-- ===== Header ===== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-[28px] font-extrabold tracking-tight">Komisi</h1>
            <p class="mt-1 text-ink-soft max-w-[60ch]">Impor laporan pesanan dari dashboard affiliate untuk melihat link mana yang benar-benar menghasilkan uang.</p>
        </div>
        @if ($hasData)
            <div class="segmented self-start" role="group" aria-label="Rentang waktu">
                @foreach ([30 => '30 hari', 90 => '90 hari', 0 => 'Semua'] as $range => $label)
                    <button type="button" wire:click="setDays({{ $range }})" aria-pressed="{{ $days === $range ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($hasData)
        {{-- ===== Summary ===== --}}
        <section class="panel p-5 sm:p-6">
            <p class="text-ink-soft">Komisi, {{ $days === 0 ? 'sepanjang waktu' : $days . ' hari terakhir' }}</p>
            <p class="mt-1 text-4xl sm:text-5xl font-extrabold tracking-tight num">{{ $rp($earned) }}</p>

            <dl class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-0 lg:divide-x divide-line">
                <div class="lg:pr-6">
                    <dt class="text-sm text-ink-soft">Sudah selesai</dt>
                    <dd class="mt-0.5 text-xl font-bold num">{{ $rp($summary['completed']) }}</dd>
                    <dd class="text-[13px] text-ink-soft">Pesanan diterima pembeli</dd>
                </div>
                <div class="lg:px-6">
                    <dt class="text-sm text-ink-soft">Masih menunggu</dt>
                    <dd class="mt-0.5 text-xl font-bold num">{{ $rp($summary['pending']) }}</dd>
                    <dd class="text-[13px] text-ink-soft">Bisa batal sebelum selesai</dd>
                </div>
                <div class="lg:px-6">
                    <dt class="text-sm text-ink-soft">Pesanan</dt>
                    <dd class="mt-0.5 text-xl font-bold num">{{ $fmt($summary['orders']) }}</dd>
                    <dd class="text-[13px] text-ink-soft">{{ $summary['cancelled'] > 0 ? $fmt($summary['cancelled']) . ' dibatalkan, tidak dihitung' : 'Tidak ada yang batal' }}</dd>
                </div>
                <div class="lg:pl-6">
                    <dt class="text-sm text-ink-soft">Komisi per klik</dt>
                    <dd class="mt-0.5 text-xl font-bold num">{{ $summary['per_click'] !== null ? $rp($summary['per_click']) : '–' }}</dd>
                    <dd class="text-[13px] text-ink-soft">
                        @if ($summary['clicks'] > 0)
                            Dari {{ $fmt($summary['clicks']) }} klik, {{ str_replace('.', ',', $summary['conversion_rate']) }}% jadi pesanan
                        @else
                            Belum ada klik di periode ini
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <div class="space-y-6 {{ $hasData ? '' : 'lg:order-2' }}">
            @if ($hasData)
                {{-- ===== Per link ===== --}}
                <section class="panel overflow-hidden">
                    <div class="px-5 sm:px-6 pt-5 sm:pt-6 pb-3">
                        <h2 class="font-bold">Per link</h2>
                        <p class="text-sm text-ink-soft">Diurutkan dari komisi terbesar. Komisi per klik membantu memilih produk yang layak dipromosikan lagi.</p>
                    </div>

                    @if ($perLink->isEmpty())
                        <p class="px-5 sm:px-6 pb-6 text-sm text-ink-soft">Belum ada pesanan yang terhubung ke link di periode ini.</p>
                    @else
                        <div class="hidden sm:grid grid-cols-[minmax(0,1fr)_5rem_5rem_7.5rem_6.5rem] gap-3 px-6 py-2 border-y border-line bg-paper text-[13px] font-semibold text-ink-soft">
                            <span>Link</span><span class="text-right">Klik</span><span class="text-right">Pesanan</span><span class="text-right">Komisi</span><span class="text-right">Per klik</span>
                        </div>
                        <ul class="divide-y divide-line border-t border-line sm:border-t-0">
                            @foreach ($perLink as $index => $row)
                                <li class="px-5 sm:px-6 py-3 sm:grid sm:grid-cols-[minmax(0,1fr)_5rem_5rem_7.5rem_6.5rem] sm:gap-3 sm:items-center">
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate" title="{{ $row['link']->title }}">{{ $row['link']->title }}</p>
                                        <p class="text-[13px] text-ink-soft truncate">/{{ $row['link']->slug }}</p>
                                    </div>
                                    <p class="mt-1 sm:mt-0 flex sm:block gap-4 text-sm sm:text-right num">
                                        <span class="sm:hidden text-ink-soft">Klik</span> {{ $fmt($row['clicks']) }}
                                    </p>
                                    <p class="flex sm:block gap-4 text-sm sm:text-right num">
                                        <span class="sm:hidden text-ink-soft">Pesanan</span> {{ $fmt($row['orders']) }}
                                    </p>
                                    <p class="flex sm:block gap-4 text-sm sm:text-right font-bold num">
                                        <span class="sm:hidden font-normal text-ink-soft">Komisi</span>
                                        <span @class(['marker' => $index === 0])>{{ $rp($row['commission']) }}</span>
                                    </p>
                                    <p class="flex sm:block gap-4 text-sm sm:text-right text-ink-soft num">
                                        <span class="sm:hidden">Per klik</span> {{ $row['per_click'] !== null ? $rp($row['per_click']) : '–' }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- ===== Unmatched ===== --}}
                @if ($unmatchedCount > 0)
                    <section class="panel p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-bold">{{ $fmt($unmatchedCount) }} pesanan belum terhubung ke link</h2>
                                <p class="mt-0.5 text-sm text-ink-soft max-w-[60ch]">Racunin mencocokkan lewat Sub ID atau ID produk. Pilih link-nya di sini; produk yang sama akan otomatis cocok di impor berikutnya.</p>
                            </div>
                            <button type="button" wire:click="rematch" wire:loading.attr="disabled" wire:target="rematch" class="btn btn-sm btn-secondary">
                                <x-icon name="refresh" /> Cocokkan ulang
                            </button>
                        </div>

                        <ul class="mt-4 divide-y divide-line border-y border-line">
                            @foreach ($unmatched as $group)
                                <li class="py-3 flex flex-col gap-2 md:flex-row md:items-center md:gap-4" wire:key="unmatched-{{ md5($group->item_key_group) }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold leading-snug line-clamp-2">{{ $group->product_name ?: 'Produk tanpa nama' }}</p>
                                        <p class="text-[13px] text-ink-soft">{{ implode(', ', array_filter([
                                            $fmt($group->orders) . ' pesanan',
                                            $rp($group->commission),
                                            $group->sub_id ? 'Sub ID “' . $group->sub_id . '”' : null,
                                            $group->item_ref ? 'ID produk ' . $group->item_ref : null,
                                        ])) }}</p>
                                    </div>
                                    <div class="flex gap-2 md:w-80">
                                        <label class="sr-only" for="assign-{{ md5($group->item_key_group) }}">Link untuk {{ $group->product_name }}</label>
                                        <select id="assign-{{ md5($group->item_key_group) }}" wire:model="assign.{{ md5($group->item_key_group) }}" class="field h-9 text-sm min-w-0">
                                            <option value="">Pilih link…</option>
                                            @foreach ($links as $l)
                                                <option value="{{ $l->id }}">{{ Str::limit($l->title, 40) }} (/{{ $l->slug }})</option>
                                            @endforeach
                                        </select>
                                        <button type="button" wire:click="assignGroup(@js($group->item_key_group))" class="btn btn-sm btn-primary shrink-0">Hubungkan</button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @else
                {{-- ===== Empty state: why and how ===== --}}
                <section class="panel p-5 sm:p-6">
                    <h2 class="font-bold">Klik saja belum cukup</h2>
                    <p class="mt-1 text-ink-soft leading-relaxed max-w-[62ch]">
                        Link dengan klik terbanyak belum tentu yang paling banyak menghasilkan komisi. Setelah laporan diimpor,
                        tiap link mendapat angka pesanan, komisi, dan komisi per klik.
                    </p>
                </section>
            @endif

            {{-- ===== Sub ID tip (a real sequence) ===== --}}
            <section class="panel p-5 sm:p-6">
                <h2 class="font-bold">Supaya pesanan cocok otomatis</h2>
                <ol class="mt-3 space-y-3 text-sm">
                    <li class="flex gap-3"><span class="font-extrabold text-racun num">1</span><span>Saat membuat link di dashboard affiliate (misalnya Shopee Affiliate), isi <span class="font-semibold">Sub ID</span>, misalnya nama slug tanpa tanda hubung: <span class="font-semibold">serumglowing</span>.</span></li>
                    <li class="flex gap-3"><span class="font-extrabold text-racun num">2</span><span>Tempel link itu di Racunin. Sub ID biasanya terbaca sendiri; kalau tidak, isi di kolom Sub ID saat edit link.</span></li>
                    <li class="flex gap-3"><span class="font-extrabold text-racun num">3</span><span>Tanpa Sub ID, Racunin tetap mencoba mencocokkan lewat ID produk. Satu produk yang punya beberapa link perlu Sub ID agar tidak tertukar.</span></li>
                </ol>
            </section>
        </div>

        {{-- ===== Import ===== --}}
        <aside class="space-y-6 {{ $hasData ? '' : 'lg:order-1' }}">
            <section class="panel p-5 sm:p-6">
                <h2 class="font-bold">Impor laporan</h2>
                <p class="mt-0.5 text-sm text-ink-soft">Ekspor laporan pesanan atau konversi sebagai CSV dari dashboard affiliate. Impor ulang file terbaru untuk memperbarui status pesanan.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="platform" class="field-label">Dari</label>
                        <select id="platform" wire:model="platform" class="field">
                            @foreach (\App\Livewire\Dashboard\Commissions::PLATFORMS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    @unless ($reviewing)
                        <div>
                            <label for="report" class="field-label">File CSV</label>
                            <label class="flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-line-strong bg-paper px-4 py-6 text-center cursor-pointer hover:border-racun">
                                <x-icon name="upload" class="size-5 text-ink-soft" />
                                <span class="text-sm font-semibold" wire:loading.remove wire:target="report">Pilih file laporan</span>
                                <span class="text-sm font-semibold" wire:loading wire:target="report">Membaca file…</span>
                                <span class="text-[13px] text-ink-soft">CSV, maksimal 10 MB</span>
                                <input type="file" id="report" wire:model="report" accept=".csv,text/csv,text/plain" class="sr-only">
                            </label>
                            @error('report') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <p class="text-sm"><span class="font-semibold">{{ $report?->getClientOriginalName() }}</span>, {{ $fmt($rowCount) }} baris</p>
                            <p class="mt-0.5 text-[13px] text-ink-soft">Cek kolom yang dikenali. Contoh isinya dari baris pertama.</p>

                            <div class="mt-3 space-y-3">
                                @foreach ($fieldLabels as $field => $label)
                                    @php $selected = $mapping[$field] ?? ''; @endphp
                                    <div>
                                        <label for="map-{{ $field }}" class="text-[13px] font-semibold">
                                            {{ $label }}
                                            @if (in_array($field, \App\Support\CommissionReportReader::REQUIRED_FIELDS, true)) <span class="text-alert">wajib</span> @endif
                                        </label>
                                        <select id="map-{{ $field }}" wire:model.live="mapping.{{ $field }}" class="field h-9 text-sm mt-1">
                                            <option value="">Tidak ada</option>
                                            @foreach ($headers as $index => $header)
                                                <option value="{{ $index }}">{{ Str::limit($header, 40) }}</option>
                                            @endforeach
                                        </select>
                                        @error('mapping.' . $field) <p class="field-error">{{ $message }}</p> @enderror
                                        @if ($selected !== '' && isset($sampleRow[(int) $selected]) && $sampleRow[(int) $selected] !== '')
                                            <p class="mt-0.5 text-[13px] text-ink-soft truncate">Contoh: {{ Str::limit($sampleRow[(int) $selected], 50) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @error('report') <p class="field-error mt-3">{{ $message }}</p> @enderror

                            <div class="mt-4 flex gap-2">
                                <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" class="btn btn-primary flex-1">
                                    <span wire:loading.remove wire:target="import">Impor {{ $fmt($rowCount) }} baris</span>
                                    <span wire:loading wire:target="import">Mengimpor…</span>
                                </button>
                                <button type="button" wire:click="cancelImport" class="btn btn-ghost">Batal</button>
                            </div>
                        </div>
                    @endunless
                </div>
            </section>

            @if ($imports->isNotEmpty())
                <section class="panel p-5 sm:p-6">
                    <h2 class="font-bold">Impor terakhir</h2>
                    <ul class="mt-3 space-y-3">
                        @foreach ($imports as $import)
                            <li class="text-sm">
                                <p class="font-semibold truncate" title="{{ $import->filename }}">{{ $import->filename }}</p>
                                <p class="text-[13px] text-ink-soft">
                                    {{ \App\Livewire\Dashboard\Commissions::PLATFORMS[$import->platform] ?? $import->platform }},
                                    {{ $import->created_at->translatedFormat('j M Y, H:i') }}.
                                    {{ $fmt($import->rows_matched) }} dari {{ $fmt($import->rows_imported) }} pesanan cocok.
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</div>
