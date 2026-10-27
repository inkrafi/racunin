<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-6" x-data="{ copied: null, copy(key, text) { navigator.clipboard.writeText(text); this.copied = key; setTimeout(() => this.copied = null, 1800) } }">
    @php
        $generators = [
            'landing' => ['action' => 'generateLandingPage', 'has' => ! empty($link?->ai_landing_page), 'loading' => 'Menulis landing page… biasanya 15–30 detik.'],
            'ads' => ['action' => 'generateAdCopy', 'has' => ! empty($link?->ai_ad_copy), 'loading' => 'Menulis variasi iklan Meta dan TikTok…'],
            'script' => ['action' => 'generateVideoScripts', 'has' => ! empty($link?->ai_video_scripts), 'loading' => 'Menulis script video scene per scene…'],
        ];
        $current = $generators[$tab];
    @endphp

    {{-- ===== Header ===== --}}
    <div>
        <h1 class="text-2xl sm:text-[28px] font-extrabold tracking-tight">AI Agent</h1>
        <p class="mt-1 text-ink-soft max-w-[60ch]">Pilih produk, lalu biarkan AI menulis landing page, iklan, dan script video. Semua hasil tersimpan di link produknya.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[320px_1fr] items-start">
        {{-- ===== Brief ===== --}}
        <section class="panel p-5 space-y-4 lg:sticky lg:top-20">
            <div>
                <label for="agent-link" class="field-label">Produk</label>
                @if ($links->isEmpty())
                    <p class="text-sm text-ink-soft">Belum ada link. <a href="{{ route('dashboard') }}" class="font-semibold text-racun">Buat link dulu</a>.</p>
                @else
                    <select id="agent-link" wire:model.live="linkId" class="field">
                        <option value="">Pilih produk…</option>
                        @foreach ($links as $option)
                            <option value="{{ $option->id }}">{{ \Illuminate\Support\Str::limit($option->title, 60) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div>
                <label for="agent-audience" class="field-label">Target audiens <span class="font-normal text-ink-soft">(opsional)</span></label>
                <input id="agent-audience" type="text" wire:model="audience" maxlength="150" class="field" placeholder="Mis. ibu muda, mahasiswa kos">
                @error('audience') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="agent-angle" class="field-label">Sudut jualan <span class="font-normal text-ink-soft">(opsional)</span></label>
                <input id="agent-angle" type="text" wire:model="angle" maxlength="150" class="field" placeholder="Mis. hemat, praktis, hadiah">
                @error('angle') <p class="field-error">{{ $message }}</p> @enderror
                <p class="field-hint">Kosongkan kalau mau AI yang memilih.</p>
            </div>

            <div>
                <label for="agent-refs" class="field-label">Link referensi <span class="font-normal text-ink-soft">(opsional, maks {{ \App\Support\LandingPageContent::MAX_REFERENCES }})</span></label>
                <textarea id="agent-refs" wire:model="references" rows="3" class="field h-auto py-2 text-sm" placeholder="https://… satu link per baris"></textarea>
                @error('references') <p class="field-error">{{ $message }}</p> @enderror
                <p class="field-hint">Halaman produk, review, atau landing page yang kamu suka. AI membaca faktanya untuk landing page, iklan, dan script, lalu menulis ulang dengan kata-katanya sendiri.</p>
            </div>

            @if ($tab === 'landing')
                <div class="pt-4 border-t border-line space-y-4">
                    <div>
                        <label for="agent-cta-text" class="field-label">Teks tombol <span class="font-normal text-ink-soft">(opsional)</span></label>
                        <input id="agent-cta-text" type="text" wire:model="ctaText" maxlength="40" class="field" placeholder="Mis. Checkout sekarang">
                        @error('ctaText') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="agent-cta-url" class="field-label">Link checkout <span class="font-normal text-ink-soft">(opsional)</span></label>
                        <input id="agent-cta-url" type="url" wire:model="ctaUrl" class="field" placeholder="https://wa.me/62… atau link toko">
                        @error('ctaUrl') <p class="field-error">{{ $message }}</p> @enderror
                        <p class="field-hint">Kosongkan untuk memakai short link produk. Klik tetap tercatat di Statistik.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="agent-original" class="field-label">Harga normal</label>
                            <input id="agent-original" type="text" inputmode="numeric" wire:model="originalPrice" class="field" placeholder="99000">
                        </div>
                        <div>
                            <span class="field-label">Harga jual</span>
                            <p class="h-10 flex items-center text-sm text-ink-soft">{{ $link?->formatted_price ?: '—' }}</p>
                        </div>
                    </div>
                    @error('originalPrice') <p class="field-error -mt-2">{{ $message }}</p> @enderror
                    <div>
                        <label for="agent-video" class="field-label">Link video <span class="font-normal text-ink-soft">(opsional)</span></label>
                        <input id="agent-video" type="url" wire:model="videoUrl" class="field" placeholder="YouTube, TikTok, Google Drive, atau .mp4">
                        @error('videoUrl') <p class="field-error">{{ $message }}</p> @enderror
                        <p class="field-hint">Untuk Google Drive, atur berbagi ke "Siapa saja yang memiliki link".</p>
                    </div>
                    <div>
                        <label for="agent-video-shape" class="field-label">Bentuk video</label>
                        <select id="agent-video-shape" wire:model="videoShape" class="field">
                            @foreach (\App\Support\LandingPageContent::VIDEO_SHAPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            @if ($tab === 'script')
                <div>
                    <span class="field-label">Durasi video</span>
                    <div class="segmented" role="group" aria-label="Durasi video">
                        @foreach (\App\Actions\AI\GenerateVideoScriptAction::DURATIONS as $seconds)
                            <button type="button" wire:click="$set('duration', {{ $seconds }})" aria-pressed="{{ $duration === $seconds ? 'true' : 'false' }}">{{ $seconds }} detik</button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($link)
                <div class="flex items-center gap-3 pt-1">
                    @if ($link->og_image_url)
                        <img src="{{ $link->og_image_url }}" alt="" class="size-12 rounded-lg object-cover border border-line shrink-0">
                    @endif
                    <div class="min-w-0 text-[13px]">
                        <p class="text-ink-soft">{{ $link->formatted_price ?: 'Harga belum diisi' }}{{ $link->discount_tag ? ' · ' . $link->discount_tag : '' }}</p>
                        <p class="text-ink-faint truncate">{{ $link->description ? \Illuminate\Support\Str::limit($link->description, 70) : 'Tambahkan deskripsi di link supaya hasil AI lebih tepat.' }}</p>
                    </div>
                </div>
            @endif
        </section>

        {{-- ===== Output ===== --}}
        <section class="panel overflow-hidden">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 py-4 border-b border-line">
                <div class="segmented self-start" role="tablist" aria-label="Jenis konten">
                    @foreach (\App\Livewire\Dashboard\AiAgent::TABS as $key => $label)
                        <button type="button" role="tab" wire:click="setTab('{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
                @if ($link)
                    <button type="button" wire:click="{{ $current['action'] }}" @if ($tab === 'landing' && $current['has']) wire:confirm="Tulis ulang akan mengganti semua teks landing page, termasuk yang sudah kamu edit. Lanjutkan?" @endif wire:loading.attr="disabled" wire:target="{{ $current['action'] }}" class="btn btn-primary self-start">
                        <x-icon name="sparkle" /> {{ $current['has'] ? 'Tulis ulang' : 'Tulis dengan AI' }}
                    </button>
                @endif
            </div>

            <div class="p-5 space-y-4">
                @if ($error)
                    <div class="flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-alert-wash text-alert text-sm">
                        <x-icon name="alert" class="mt-0.5" /> <p>{{ $error }}</p>
                    </div>
                @endif

                <div wire:loading.flex wire:target="{{ $current['action'] }}" class="items-center gap-2.5 px-3 py-2.5 rounded-lg bg-racun-wash text-racun text-sm font-medium">
                    <x-icon name="refresh" class="animate-spin" /> {{ $current['loading'] }}
                </div>

                @if (! $link)
                    <div class="py-12 text-center max-w-sm mx-auto">
                        <p class="font-semibold">Pilih produk untuk mulai</p>
                        <p class="mt-1 text-sm text-ink-soft">AI memakai judul, harga, promo, dan deskripsi link kamu. Makin lengkap datanya, makin tajam hasilnya.</p>
                    </div>
                @elseif (! $current['has'])
                    <div class="py-12 text-center max-w-sm mx-auto" wire:loading.remove wire:target="{{ $current['action'] }}">
                        @if ($tab === 'landing')
                            <p class="font-semibold">Belum ada landing page</p>
                            <p class="mt-1 text-sm text-ink-soft">Halaman pre-sell yang meyakinkan pembeli sebelum diarahkan ke toko. Tombolnya lewat short link kamu, jadi kliknya tetap tercatat.</p>
                        @elseif ($tab === 'ads')
                            <p class="font-semibold">Belum ada konten iklan</p>
                            <p class="mt-1 text-sm text-ink-soft">AI menulis 3 variasi Meta Ads dan 3 variasi TikTok Ads dengan sudut berbeda, siap dites A/B.</p>
                        @else
                            <p class="font-semibold">Belum ada script video</p>
                            <p class="mt-1 text-sm text-ink-soft">Dua script siap rekam untuk TikTok, Reels, atau Shorts: hook, scene, voice over, teks layar, sampai caption.</p>
                        @endif
                    </div>
                @else
                    <div wire:loading.class="opacity-50" wire:target="{{ $current['action'] }}">
                        @if ($tab === 'landing')
                            @include('livewire.dashboard.partials.ai-landing')
                        @elseif ($tab === 'ads')
                            @include('livewire.dashboard.partials.ai-ads', ['ads' => $link->ai_ad_copy])
                        @else
                            @include('livewire.dashboard.partials.ai-scripts', ['scripts' => $link->ai_video_scripts])
                        @endif
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
