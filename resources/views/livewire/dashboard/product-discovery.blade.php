<div class="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
    <h1 class="text-2xl sm:text-[28px] font-extrabold tracking-tight">Ide produk</h1>
    <p class="mt-1 text-ink-soft max-w-2xl">Ragu produk mana yang mau dipromosikan? Tulis nama atau idenya. AI menilai keunikan dan daya tarik emosionalnya, lalu menyarankan sudut promosi.</p>

    {{-- Tabs --}}
    <div class="mt-6 flex gap-6 border-b border-line" role="tablist">
        @foreach (['analyze' => 'Nilai produk', 'history' => 'Riwayat'] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')"
                    @class([
                        'relative -mb-px h-11 text-sm font-semibold cursor-pointer',
                        'text-ink border-b-2 border-racun' => $tab === $key,
                        'text-ink-soft hover:text-ink' => $tab !== $key,
                    ])>
                {{ $label }}
                @if ($key === 'history' && $historyCount > 0)
                    <span class="ml-1 text-ink-soft font-medium num">({{ $historyCount }})</span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($tab === 'analyze')
        <form wire:submit="analyze" class="mt-6 panel p-5 sm:p-6">
            <label for="productInput" class="field-label">Produk atau ide produk</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="text" id="productInput" wire:model="productInput" class="field h-11" maxlength="255"
                       placeholder="Lampu tidur proyektor bintang dengan bluetooth speaker">
                <button type="submit" wire:loading.attr="disabled" wire:target="analyze" class="btn btn-primary h-11 shrink-0">
                    <span wire:loading.remove wire:target="analyze" class="inline-flex items-center gap-2"><x-icon name="sparkle" /> Nilai produk</span>
                    <span wire:loading wire:target="analyze">Menilai…</span>
                </button>
            </div>
            @error('productInput') <p class="field-error">{{ $message }}</p> @else <p class="field-hint">Semakin spesifik, semakin tajam penilaiannya. Sebutkan bahan, fungsi, atau target pembelinya.</p> @enderror
        </form>

        <div wire:loading.flex wire:target="analyze" class="mt-6 items-center gap-2.5 px-4 py-3 rounded-lg bg-racun-wash text-racun text-sm font-medium">
            <x-icon name="refresh" class="animate-spin" /> AI sedang menilai produk, biasanya 5–10 detik…
        </div>

        @if ($latestDiscovery)
            <section class="mt-6 panel p-5 sm:p-6" wire:loading.remove wire:target="analyze">
                <div class="flex items-start justify-between gap-4 pb-5 mb-5 border-b border-line">
                    <div class="min-w-0">
                        <p class="text-sm text-ink-soft">Hasil penilaian</p>
                        <h2 class="mt-0.5 text-lg font-bold leading-snug">{{ $latestDiscovery->product_input }}</h2>
                    </div>
                    <button type="button" wire:click="deleteHistory({{ $latestDiscovery->id }})" wire:confirm="Hapus hasil penilaian ini?"
                            class="icon-btn shrink-0 hover:!text-alert hover:!bg-alert-wash" aria-label="Hapus hasil" title="Hapus"><x-icon name="trash" /></button>
                </div>
                @include('livewire.dashboard.partials.discovery-result', ['discovery' => $latestDiscovery])
            </section>
        @endif
    @endif

    @if ($tab === 'history')
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <label class="relative flex-1">
                <span class="sr-only">Cari riwayat</span>
                <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari produk atau sudut promosi" class="field pl-9">
            </label>
            <label class="sm:w-52">
                <span class="sr-only">Filter potensi viral</span>
                <select wire:model.live="viralFilter" class="field">
                    <option value="">Semua potensi</option>
                    <option value="Tinggi">Potensi tinggi</option>
                    <option value="Menengah">Potensi menengah</option>
                    <option value="Rendah">Potensi rendah</option>
                </select>
            </label>
        </div>

        @if ($history->isNotEmpty())
            <ul class="mt-4 panel divide-y divide-line">
                @foreach ($history as $item)
                    <li wire:key="discovery-{{ $item->id }}" class="p-4 sm:px-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold leading-snug">{{ $item->product_input }}</p>
                            <p class="mt-1 text-[13px] text-ink-soft line-clamp-1">{{ $item->pitch_angle }}</p>
                        </div>
                        <dl class="flex gap-5 text-[13px] shrink-0">
                            <div><dt class="text-ink-soft">Potensi</dt><dd class="font-semibold">{{ $item->viral_potential }}</dd></div>
                            <div><dt class="text-ink-soft">Keunikan</dt><dd class="font-semibold num">{{ $item->uniqueness_score }}</dd></div>
                            <div><dt class="text-ink-soft">Emosional</dt><dd class="font-semibold num">{{ $item->emotional_score }}</dd></div>
                            <div class="hidden md:block"><dt class="text-ink-soft">Dinilai</dt><dd>{{ $item->created_at->diffForHumans() }}</dd></div>
                        </dl>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" wire:click="viewDetail({{ $item->id }})" class="btn btn-sm btn-secondary">Lihat</button>
                            <button type="button" wire:click="deleteHistory({{ $item->id }})" wire:confirm="Hapus hasil penilaian ini?"
                                    class="icon-btn hover:!text-alert hover:!bg-alert-wash" aria-label="Hapus" title="Hapus"><x-icon name="trash" /></button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($history->hasPages())
                <div class="mt-4">{{ $history->links() }}</div>
            @endif
        @else
            <div class="mt-4 panel px-6 py-14 text-center">
                @if (! empty($search) || ! empty($viralFilter))
                    <p class="font-semibold">Tidak ada hasil yang cocok</p>
                    <p class="mt-1 text-sm text-ink-soft">Coba kata kunci lain atau tampilkan semua potensi.</p>
                @else
                    <p class="font-semibold">Belum ada produk yang dinilai</p>
                    <p class="mt-1 text-sm text-ink-soft">Hasil penilaian tersimpan di sini supaya bisa kamu bandingkan.</p>
                    <button type="button" wire:click="setTab('analyze')" class="btn btn-primary mt-4">Nilai produk pertama</button>
                @endif
            </div>
        @endif
    @endif

    {{-- Detail --}}
    @if ($showDetailModal && $selectedDiscovery)
        <div class="modal-backdrop" wire:click.self="closeDetailModal" @keydown.escape.window="$wire.closeDetailModal()">
            <div class="modal-sheet animate-sheet sm:max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="discovery-title">
                <div class="flex items-start justify-between gap-4 px-5 sm:px-6 py-4 border-b border-line shrink-0">
                    <div class="min-w-0">
                        <h2 id="discovery-title" class="text-lg font-bold leading-snug">{{ $selectedDiscovery->product_input }}</h2>
                        <p class="text-sm text-ink-soft">Dinilai {{ $selectedDiscovery->created_at->translatedFormat('j F Y, H.i') }}</p>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="icon-btn -mr-2 shrink-0" aria-label="Tutup"><x-icon name="x" class="size-5" /></button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5">
                    @include('livewire.dashboard.partials.discovery-result', ['discovery' => $selectedDiscovery])
                </div>
                <div class="flex items-center justify-between gap-2 px-5 sm:px-6 py-3 border-t border-line shrink-0">
                    <button type="button" wire:click="deleteHistory({{ $selectedDiscovery->id }})" wire:confirm="Hapus hasil penilaian ini?" class="btn btn-ghost text-alert hover:!bg-alert-wash">
                        <x-icon name="trash" /> Hapus
                    </button>
                    <button type="button" wire:click="closeDetailModal" class="btn btn-secondary">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
