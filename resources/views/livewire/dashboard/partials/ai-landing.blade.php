@php
    $live = $link->hasPublishedLandingPage();
    $pairEditors = [
        'benefits' => ['label' => 'Manfaat', 'a' => 'title', 'b' => 'desc', 'aLabel' => 'Manfaat', 'bLabel' => 'Penjelasan', 'max' => 6],
        'steps' => ['label' => 'Cara pakai', 'a' => 'title', 'b' => 'desc', 'aLabel' => 'Langkah', 'bLabel' => 'Penjelasan', 'max' => 5],
        'faq' => ['label' => 'FAQ', 'a' => 'q', 'b' => 'a', 'aLabel' => 'Pertanyaan', 'bLabel' => 'Jawaban', 'max' => 6],
    ];
    $swatches = ['glow' => ['#FCEEEE', '#C2255C'], 'gadget' => ['#EDF0F4', '#2450FF'], 'dapur' => ['#FFF4D6', '#D93A1A'], 'belajar' => ['#EEF3EC', '#1E7A46'], 'racun' => ['#F7F0FA', '#E0186C']];
@endphp

{{-- ===== Publish bar ===== --}}
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-lg border border-line p-4">
    <div class="min-w-0">
        <p class="text-sm font-semibold">{{ $live ? 'Online' : 'Belum online' }}</p>
        @if ($live)
            <a href="{{ $link->landing_page_url }}" target="_blank" rel="noopener" class="text-sm text-racun font-semibold break-all">{{ preg_replace('#^https?://#', '', $link->landing_page_url) }}</a>
        @elseif ($link->lp_published_at && ! $link->isActive())
            <p class="text-[13px] text-ink-soft">Link produk sedang nonaktif, jadi halaman ini tidak tampil.</p>
        @else
            <p class="text-[13px] text-ink-soft">Cek tampilannya lewat Preview, lalu online-kan untuk dipakai di bio atau iklan.</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2 shrink-0">
        <a href="{{ $link->landing_page_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-secondary"><x-icon name="external" /> {{ $live ? 'Buka' : 'Preview' }}</a>
        @if ($live)
            <button type="button" class="btn btn-sm btn-secondary" @click="copy('lp-url', @js($link->landing_page_url))">
                <x-icon name="copy" /> <span x-text="copied === 'lp-url' ? 'Tersalin' : 'Salin link'"></span>
            </button>
        @endif
        <button type="button" wire:click="togglePublish" class="btn btn-sm {{ $link->lp_published_at ? 'btn-ghost' : 'btn-primary' }}">
            {{ $link->lp_published_at ? 'Sembunyikan' : 'Online-kan' }}
        </button>
    </div>
</div>

{{-- ===== Funnel (last 30 days) ===== --}}
@if ($landingStats)
    <div class="mt-3 rounded-lg border border-line p-4">
        <p class="text-[13px] text-ink-soft">30 hari terakhir</p>
        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
                <dt class="text-[13px] text-ink-soft">Kunjungan</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ number_format($landingStats['views'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-[13px] text-ink-soft">Pengunjung unik</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ number_format($landingStats['unique_views'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-[13px] text-ink-soft">Klik tombol beli</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ number_format($landingStats['clicks'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-[13px] text-ink-soft">Konversi ke toko</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ $landingStats['rate'] === null ? '–' : str_replace('.', ',', $landingStats['rate']) . '%' }}</dd>
            </div>
        </dl>
        @if ($landingStats['views'] === 0)
            <p class="mt-2 text-[13px] text-ink-soft">Belum ada kunjungan. Kunjunganmu sendiri tidak dihitung.</p>
        @endif
    </div>
@endif

{{-- ===== Editor ===== --}}
<form wire:submit="saveLandingPage" class="mt-5 space-y-6">
    <fieldset>
        <legend class="field-label">Tema</legend>
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (\App\Support\LandingPageContent::THEMES as $key => $label)
                <label class="flex items-center gap-3 rounded-lg border p-3 cursor-pointer has-[:checked]:border-racun has-[:checked]:bg-racun-wash border-line">
                    <input type="radio" wire:model="lp.theme" value="{{ $key }}" class="sr-only">
                    <span class="relative size-8 rounded-full shrink-0 border border-line" style="background: {{ $swatches[$key][0] }}">
                        <span class="absolute right-0 bottom-0 size-3.5 rounded-full" style="background: {{ $swatches[$key][1] }}"></span>
                    </span>
                    <span class="text-sm leading-snug">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="lp-headline" class="field-label">Headline</label>
            <input id="lp-headline" type="text" wire:model="lp.headline" maxlength="140" class="field">
            @error('lp.headline') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="lp-sub" class="field-label">Subheadline</label>
            <textarea id="lp-sub" wire:model="lp.subheadline" rows="2" maxlength="240" class="field h-auto py-2"></textarea>
        </div>
        <div>
            <label for="lp-cta" class="field-label">Teks tombol</label>
            <input id="lp-cta" type="text" wire:model="lp.cta_text" maxlength="40" class="field">
        </div>
        <div>
            <label for="lp-promo" class="field-label">Promo berakhir <span class="font-normal text-ink-soft">(opsional, WIB)</span></label>
            <input id="lp-promo" type="datetime-local" wire:model="lp.promo_ends_at" class="field">
            @error('lp.promo_ends_at') <p class="field-error">{{ $message }}</p> @enderror
            <p class="field-hint">Isi hanya kalau promonya memang berakhir di waktu itu. Countdown tampil sampai waktunya habis.</p>
        </div>
    </div>

    {{-- Offer --}}
    <details class="rounded-lg border border-line" open>
        <summary class="px-4 py-3 font-semibold cursor-pointer">Penawaran, tombol, dan video</summary>
        <div class="px-4 pb-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="lp-cta-url" class="field-label">Link checkout <span class="font-normal text-ink-soft">(kosongkan untuk short link)</span></label>
                <input id="lp-cta-url" type="url" wire:model="lp.cta_url" class="field" placeholder="https://wa.me/62… atau link toko">
                @error('lp.cta_url') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lp-original" class="field-label">Harga normal <span class="font-normal text-ink-soft">(untuk label diskon)</span></label>
                <input id="lp-original" type="text" inputmode="numeric" wire:model="lp.original_price" class="field" placeholder="99000">
                @error('lp.original_price') <p class="field-error">{{ $message }}</p> @enderror
                @if ($percent = \App\Support\LandingPageContent::discountPercent(['original_price' => (int) preg_replace('/[^0-9]/', '', (string) ($lp['original_price'] ?? ''))], $link))
                    <p class="field-hint">Tampil sebagai diskon {{ $percent }}% dari harga jual {{ $link->formatted_price }}.</p>
                @endif
            </div>
            <div>
                <label for="lp-video" class="field-label">Link video</label>
                <input id="lp-video" type="url" wire:model="lp.video_url" class="field" placeholder="YouTube, TikTok, Google Drive, atau .mp4">
                @error('lp.video_url') <p class="field-error">{{ $message }}</p> @enderror
                <p class="field-hint">Untuk Google Drive, atur berbagi ke "Siapa saja yang memiliki link".</p>
            </div>
            <div>
                <label for="lp-video-shape" class="field-label">Bentuk video</label>
                <select id="lp-video-shape" wire:model="lp.video_shape" class="field">
                    @foreach (\App\Support\LandingPageContent::VIDEO_SHAPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </details>

    {{-- Gallery --}}
    <div>
        <p class="field-label">Foto tambahan</p>
        <p class="field-hint -mt-1 mb-2">Foto utama diambil dari link produk. Tambahkan sampai {{ \App\Support\LandingPageContent::MAX_IMAGES }} link foto lain (klik kanan foto di halaman toko, lalu salin alamat gambar).</p>
        <div class="space-y-2">
            @foreach ($lp['images'] ?? [] as $i => $url)
                <div class="flex gap-2" wire:key="lp-image-{{ $i }}">
                    <input type="url" wire:model="lp.images.{{ $i }}" placeholder="https://…" class="field" aria-label="Link foto {{ $i + 1 }}">
                    <button type="button" wire:click="removeLandingItem('images', {{ $i }})" class="icon-btn shrink-0" aria-label="Hapus foto {{ $i + 1 }}"><x-icon name="trash" /></button>
                </div>
                @error('lp.images.' . $i) <p class="field-error">{{ $message }}</p> @enderror
            @endforeach
        </div>
        @if (count($lp['images'] ?? []) < \App\Support\LandingPageContent::MAX_IMAGES)
            <button type="button" wire:click="addLandingItem('images')" class="btn btn-sm btn-ghost mt-2"><x-icon name="plus" /> Tambah foto</button>
        @endif
    </div>

    {{-- Story sections --}}
    <details class="rounded-lg border border-line group" open>
        <summary class="px-4 py-3 font-semibold cursor-pointer">Masalah dan solusi</summary>
        <div class="px-4 pb-4 grid gap-4">
            <div>
                <label for="lp-problem-title" class="field-label">Judul bagian masalah</label>
                <input id="lp-problem-title" type="text" wire:model="lp.problem_title" class="field">
            </div>
            <div>
                <label for="lp-pains" class="field-label">Masalah audiens <span class="font-normal text-ink-soft">(satu per baris)</span></label>
                <textarea id="lp-pains" wire:model="lp.pain_points" rows="4" class="field h-auto py-2"></textarea>
            </div>
            <div>
                <label for="lp-solution-title" class="field-label">Judul bagian solusi</label>
                <input id="lp-solution-title" type="text" wire:model="lp.solution_title" class="field">
            </div>
            <div>
                <label for="lp-solution" class="field-label">Paragraf solusi</label>
                <textarea id="lp-solution" wire:model="lp.solution" rows="3" class="field h-auto py-2"></textarea>
            </div>
            <div>
                <label for="lp-benefits-title" class="field-label">Judul bagian manfaat</label>
                <input id="lp-benefits-title" type="text" wire:model="lp.benefits_title" class="field">
            </div>
        </div>
    </details>

    @foreach ($pairEditors as $field => $cfg)
        <details class="rounded-lg border border-line">
            <summary class="px-4 py-3 font-semibold cursor-pointer">{{ $cfg['label'] }} <span class="font-normal text-ink-soft">({{ count($lp[$field] ?? []) }})</span></summary>
            <div class="px-4 pb-4 space-y-3">
                @foreach ($lp[$field] ?? [] as $i => $item)
                    <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr_auto] items-start" wire:key="lp-{{ $field }}-{{ $i }}">
                        <input type="text" wire:model="lp.{{ $field }}.{{ $i }}.{{ $cfg['a'] }}" class="field" placeholder="{{ $cfg['aLabel'] }}" aria-label="{{ $cfg['aLabel'] }} {{ $i + 1 }}">
                        <textarea wire:model="lp.{{ $field }}.{{ $i }}.{{ $cfg['b'] }}" rows="2" class="field h-auto py-2" placeholder="{{ $cfg['bLabel'] }}" aria-label="{{ $cfg['bLabel'] }} {{ $i + 1 }}"></textarea>
                        <button type="button" wire:click="removeLandingItem('{{ $field }}', {{ $i }})" class="icon-btn" aria-label="Hapus {{ mb_strtolower($cfg['aLabel']) }} {{ $i + 1 }}"><x-icon name="trash" /></button>
                    </div>
                @endforeach
                @if (count($lp[$field] ?? []) < $cfg['max'])
                    <button type="button" wire:click="addLandingItem('{{ $field }}')" class="btn btn-sm btn-ghost"><x-icon name="plus" /> Tambah {{ mb_strtolower($cfg['aLabel']) }}</button>
                @endif
                @if ($field === 'steps')
                    <p class="field-hint">Kosongkan semua langkah untuk menyembunyikan bagian ini.</p>
                @endif
            </div>
        </details>
    @endforeach

    <details class="rounded-lg border border-line">
        <summary class="px-4 py-3 font-semibold cursor-pointer">Perbandingan, target, dan penutup</summary>
        <div class="px-4 pb-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="lp-without" class="field-label">Tanpa produk <span class="font-normal text-ink-soft">(satu per baris)</span></label>
                <textarea id="lp-without" wire:model="lp.comparison_without" rows="4" class="field h-auto py-2"></textarea>
            </div>
            <div>
                <label for="lp-with" class="field-label">Dengan produk <span class="font-normal text-ink-soft">(satu per baris)</span></label>
                <textarea id="lp-with" wire:model="lp.comparison_with" rows="4" class="field h-auto py-2"></textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="lp-for" class="field-label">Cocok untuk <span class="font-normal text-ink-soft">(satu per baris)</span></label>
                <textarea id="lp-for" wire:model="lp.suitable_for" rows="3" class="field h-auto py-2"></textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="lp-closing-title" class="field-label">Judul penutup</label>
                <input id="lp-closing-title" type="text" wire:model="lp.closing_title" class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="lp-closing" class="field-label">Kalimat penutup</label>
                <textarea id="lp-closing" wire:model="lp.closing" rows="2" class="field h-auto py-2"></textarea>
            </div>
        </div>
    </details>

    <div class="flex items-center justify-end gap-2 pt-2 border-t border-line">
        <span wire:dirty wire:target="lp" class="text-[13px] text-ink-soft mr-auto">Ada perubahan yang belum disimpan</span>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveLandingPage">Simpan perubahan</button>
    </div>
</form>
