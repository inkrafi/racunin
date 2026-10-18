<div>
    @if ($show)
        <div class="modal-backdrop" wire:click.self="close" @keydown.escape.window="$wire.close()">
            <div class="modal-sheet animate-sheet sm:max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="bulk-title">
                <div class="flex items-start justify-between gap-4 px-5 sm:px-6 py-4 border-b border-line shrink-0">
                    <div>
                        <h2 id="bulk-title" class="text-lg font-bold">Impor banyak link</h2>
                        <p class="text-sm text-ink-soft">Tempel sampai 25 link produk dari Shopee, Tokopedia, TikTok Shop, Lazada, dan lainnya. Judul, foto, dan harga dicoba diambil otomatis.</p>
                    </div>
                    <button type="button" wire:click="close" class="icon-btn -mr-2 shrink-0" aria-label="Tutup"><x-icon name="x" class="size-5" /></button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5 space-y-5">
                    @if (empty($items))
                        <div>
                            <label for="bulk_urls" class="field-label">Link produk, satu per baris</label>
                            <textarea id="bulk_urls" wire:model="urlsInput" rows="7" class="field"
                                      placeholder="https://s.shopee.co.id/abc123&#10;https://vt.tokopedia.com/t/xyz&#10;https://s.lazada.co.id/def456"></textarea>
                            @if ($error) <p class="field-error">{{ $error }}</p> @endif
                        </div>
                    @else
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="font-semibold">{{ count($items) }} produk siap disimpan</p>
                                <button type="button" wire:click="$set('items', [])" class="text-sm text-racun font-semibold hover:underline cursor-pointer">Ubah daftar link</button>
                            </div>
                            <p class="text-sm text-ink-soft">Periksa judulnya. Hapus yang tidak ingin disimpan.</p>
                            @if ($error) <p class="field-error">{{ $error }}</p> @endif
                        </div>

                        <ul class="divide-y divide-line rounded-lg border border-line">
                            @foreach ($items as $index => $item)
                                <li wire:key="bulk-item-{{ $index }}" class="flex items-start gap-3 p-3">
                                    @if (! empty($item['og_image_url']))
                                        <img src="{{ $item['og_image_url'] }}" alt="" class="size-12 rounded-lg object-cover border border-line shrink-0">
                                    @else
                                        <span class="size-12 rounded-lg bg-paper border border-line inline-flex items-center justify-center text-ink-faint shrink-0"><x-icon name="bag" class="size-5" /></span>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <label class="sr-only" for="bulk-title-{{ $index }}">Judul produk {{ $index + 1 }}</label>
                                        <input id="bulk-title-{{ $index }}" type="text" wire:model="items.{{ $index }}.title" class="field h-9 font-semibold">
                                        <p class="mt-1 flex flex-wrap gap-x-3 text-[13px] text-ink-soft">
                                            <span class="font-semibold text-ink">/{{ $item['slug'] }}</span>
                                            @if (! empty($item['price']))
                                                <span class="num">Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
                                            @endif
                                            <span>{{ $item['category'] }}</span>
                                        </p>
                                    </div>
                                    <button type="button" wire:click="removeItem({{ $index }})" class="icon-btn shrink-0 hover:!text-alert hover:!bg-alert-wash" aria-label="Hapus dari daftar"><x-icon name="x" /></button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 px-5 sm:px-6 py-3 border-t border-line shrink-0">
                    <button type="button" wire:click="close" class="btn btn-ghost">Batal</button>
                    @if (empty($items))
                        <button type="button" wire:click="process" wire:loading.attr="disabled" wire:target="process" class="btn btn-primary">
                            <span wire:loading.remove wire:target="process">Ambil detail produk</span>
                            <span wire:loading wire:target="process">Mengambil detail…</span>
                        </button>
                    @else
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">
                            <span wire:loading.remove wire:target="save">Simpan {{ count($items) }} link</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
