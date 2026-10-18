<div>
    @if ($show && $link)
        @php $hasCaptions = ! empty($captions['twitter']) || ! empty($captions['threads']); @endphp

        <div class="modal-backdrop" wire:click.self="close" @keydown.escape.window="$wire.close()">
            <div class="modal-sheet animate-sheet sm:max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="caption-title" x-data="{ copied: null }">
                <div class="flex items-start justify-between gap-4 px-5 sm:px-6 py-4 border-b border-line shrink-0">
                    <div class="min-w-0">
                        <h2 id="caption-title" class="text-lg font-bold">Caption AI</h2>
                        <p class="text-sm text-ink-soft truncate">{{ $link->title }}</p>
                    </div>
                    <button type="button" wire:click="close" class="icon-btn -mr-2 shrink-0" aria-label="Tutup"><x-icon name="x" class="size-5" /></button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5 space-y-4">
                    @if ($error)
                        <div class="flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-alert-wash text-alert text-sm">
                            <x-icon name="alert" class="mt-0.5" /> <p>{{ $error }}</p>
                        </div>
                    @endif

                    <div wire:loading.flex wire:target="generate" class="items-center gap-2.5 px-3 py-2.5 rounded-lg bg-racun-wash text-racun text-sm font-medium">
                        <x-icon name="refresh" class="animate-spin" /> Menulis caption untuk X dan Threads…
                    </div>

                    @if ($hasCaptions)
                        <div class="grid gap-3 sm:grid-cols-2" wire:loading.class="opacity-50" wire:target="generate">
                            <div class="rounded-lg border border-line p-4 flex flex-col gap-3">
                                <p class="text-[13px] font-semibold text-ink-soft">X</p>
                                <p class="flex-1 text-sm whitespace-pre-line leading-relaxed">{{ $captions['twitter'] ?? '' }}</p>
                                <button type="button" class="btn btn-sm btn-secondary self-start"
                                        @click="navigator.clipboard.writeText(@js($captions['twitter'] ?? '')); copied = 'x'; setTimeout(() => copied = null, 1800)">
                                    <span x-text="copied === 'x' ? 'Tersalin' : 'Salin untuk X'"></span>
                                </button>
                            </div>
                            <div class="rounded-lg border border-line p-4 flex flex-col gap-3">
                                <p class="text-[13px] font-semibold text-ink-soft">Threads</p>
                                <p class="flex-1 text-sm whitespace-pre-line leading-relaxed">{{ $captions['threads'] ?? '' }}</p>
                                <button type="button" class="btn btn-sm btn-secondary self-start"
                                        @click="navigator.clipboard.writeText(@js($captions['threads'] ?? '')); copied = 'threads'; setTimeout(() => copied = null, 1800)">
                                    <span x-text="copied === 'threads' ? 'Tersalin' : 'Salin untuk Threads'"></span>
                                </button>
                            </div>
                        </div>
                        <p class="text-[13px] text-ink-soft">Caption menyertakan short link <span class="font-semibold text-ink">{{ $link->short_url }}</span>, jadi klik dari postingan ikut tercatat.</p>
                    @else
                        <div class="py-8 text-center max-w-sm mx-auto" wire:loading.remove wire:target="generate">
                            <p class="font-semibold">Belum ada caption untuk link ini</p>
                            <p class="mt-1 text-sm text-ink-soft">AI menulis dua versi: singkat untuk X, santai untuk Threads. Keduanya sudah memuat short link kamu.</p>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 px-5 sm:px-6 py-3 border-t border-line shrink-0">
                    <button type="button" wire:click="close" class="btn btn-ghost">Tutup</button>
                    <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" class="btn btn-primary">
                        <x-icon name="sparkle" /> {{ $hasCaptions ? 'Tulis ulang' : 'Tulis caption' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
