<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10"
     x-data="{
        copiedId: null,
        copy(id, text) {
            navigator.clipboard.writeText(text);
            this.copiedId = id;
            setTimeout(() => this.copiedId = null, 1800);
        }
     }">
    @php
        $shortDomain = preg_replace('#^https?://#', '', rtrim(url('/'), '/')) . '/';
        $filtering = $search !== '' || $statusFilter !== 'all';
    @endphp

    {{-- ===== Header ===== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-[28px] font-extrabold tracking-tight">{{ $isAdmin ? 'Semua link' : 'Link kamu' }}</h1>
            <p class="mt-1 text-ink-soft">
                <span class="num">{{ number_format($stats['active_links'], 0, ',', '.') }}</span> dari <span class="num">{{ number_format($stats['total_links'], 0, ',', '.') }}</span> link aktif,
                <span class="num font-semibold text-ink">{{ number_format($weekClicks, 0, ',', '.') }}</span> klik dalam 7 hari terakhir.
            </p>
            <a href="{{ route('dashboard.link-test') }}" class="mt-1 inline-block text-sm font-semibold text-racun hover:underline">Tes apakah link membuka aplikasi toko di HP</a>
        </div>

        <div class="flex gap-2">
            <button type="button" wire:click="$dispatch('open-bulk-import')" class="btn btn-secondary flex-1 sm:flex-none">
                <x-icon name="stack" /> Impor banyak link
            </button>
            <button type="button" wire:click="openCreateModal" class="btn btn-primary flex-1 sm:flex-none">
                <x-icon name="plus" /> Buat link
            </button>
        </div>
    </div>

    {{-- ===== Search & filter ===== --}}
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative flex-1">
            <span class="sr-only">Cari link</span>
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari judul, slug, atau URL" class="field pl-9">
        </label>
        <div class="segmented self-start" role="group" aria-label="Filter status">
            <button type="button" wire:click="$set('statusFilter', 'all')" aria-pressed="{{ $statusFilter === 'all' ? 'true' : 'false' }}">Semua</button>
            <button type="button" wire:click="$set('statusFilter', 'active')" aria-pressed="{{ $statusFilter === 'active' ? 'true' : 'false' }}">Aktif</button>
            <button type="button" wire:click="$set('statusFilter', 'inactive')" aria-pressed="{{ $statusFilter === 'inactive' ? 'true' : 'false' }}">Nonaktif</button>
        </div>
    </div>

    {{-- ===== Links list ===== --}}
    <div class="mt-4 panel overflow-hidden">
        <div wire:loading.delay wire:target="search,statusFilter,clearFilters" class="divide-y divide-line">
            @foreach (range(1, 3) as $i)
                <div class="flex items-center gap-4 p-4">
                    <div class="skeleton size-12 shrink-0"></div>
                    <div class="flex-1 space-y-2"><div class="skeleton h-4 w-1/2"></div><div class="skeleton h-3 w-1/3"></div></div>
                </div>
            @endforeach
        </div>

        <div wire:loading.remove.delay wire:target="search,statusFilter,clearFilters">
            @if ($links->count() > 0)
                <ul class="divide-y divide-line">
                    @foreach ($links as $link)
                        @php
                            $clicks = $recentClicks[$link->id] ?? ['total' => 0, 'daily' => array_fill(0, 7, 0)];
                            $hasCaptions = ! empty($link->ai_captions['twitter']) || ! empty($link->ai_captions['threads']);
                            $active = $link->isActive();
                        @endphp
                        <li wire:key="link-{{ $link->id }}" class="p-4 sm:px-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,17rem)_8rem_13.5rem] lg:items-center lg:gap-6 {{ $active ? '' : 'bg-paper/60' }}">

                            {{-- Product --}}
                            <div class="flex items-center gap-3 min-w-0">
                                @if ($link->og_image_url)
                                    <img src="{{ $link->og_image_url }}" alt="" loading="lazy" class="size-12 rounded-lg object-cover border border-line shrink-0 {{ $active ? '' : 'grayscale opacity-70' }}">
                                @else
                                    <span class="size-12 rounded-lg bg-paper border border-line inline-flex items-center justify-center text-ink-faint shrink-0"><x-icon name="bag" class="size-5" /></span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-semibold leading-snug line-clamp-2 {{ $active ? '' : 'text-ink-soft' }}" title="{{ $link->title }}">{{ $link->title }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-ink-soft">
                                        @if ($link->platform_label)
                                            <span class="font-semibold">{{ $link->platform_label }}</span>
                                        @endif
                                        @if ($link->price)
                                            <span class="num font-semibold text-ink">{{ $link->formatted_price }}</span>
                                        @endif
                                        @if ($link->discount_tag)
                                            <span class="px-1.5 rounded bg-racun-wash text-racun font-semibold">{{ $link->discount_tag }}</span>
                                        @endif
                                        @if ($link->category)
                                            <span>{{ $link->category }}</span>
                                        @endif
                                        @if ($isAdmin && $link->user)
                                            <span>oleh {{ $link->user->name }}</span>
                                        @endif
                                        @unless ($link->is_in_catalog)
                                            <span class="inline-flex items-center gap-1" title="Tidak tampil di katalog bio"><x-icon name="eye-off" class="size-3.5" /> Tidak di katalog</span>
                                        @endunless
                                    </p>
                                </div>
                            </div>

                            {{-- Short link + copy --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <a href="{{ $link->short_url }}" target="_blank" rel="noopener" class="min-w-0 flex-1 flex text-[15px] hover:underline decoration-line-strong underline-offset-4" title="Buka {{ $link->short_url }}">
                                    {{-- The slug identifies the link; the full URL is in the tooltip and is what gets copied --}}
                                    <span class="shortlink-domain">/</span><span class="shortlink-slug truncate">{{ $link->slug }}</span>
                                </a>
                                <button type="button" @click="copy({{ $link->id }}, @js($link->short_url))"
                                        class="btn btn-sm shrink-0 w-[5.75rem]"
                                        :class="copiedId === {{ $link->id }} ? 'bg-racun-wash text-racun' : 'btn-secondary'">
                                    <span x-show="copiedId !== {{ $link->id }}" class="inline-flex items-center gap-1.5"><x-icon name="copy" /> Salin</span>
                                    <span x-show="copiedId === {{ $link->id }}" x-cloak class="inline-flex items-center gap-1.5"><x-icon name="check" /> Tersalin</span>
                                </button>
                            </div>

                            {{-- Clicks, last 7 days --}}
                            <div class="flex items-center gap-2.5" title="Klik 7 hari terakhir">
                                <x-sparkline :values="$clicks['daily']" />
                                <p class="text-[13px] text-ink-soft leading-tight">
                                    <span @class(['num block text-[15px] font-bold text-ink', 'marker' => $link->id === $topLinkId])>{{ number_format($clicks['total'], 0, ',', '.') }}</span>
                                    klik/7 hari
                                </p>
                            </div>

                            {{-- Status + actions --}}
                            <div class="flex items-center justify-between gap-3 lg:justify-end">
                                <button type="button" role="switch" aria-checked="{{ $active ? 'true' : 'false' }}"
                                        wire:click="toggleStatus({{ $link->id }})" wire:loading.attr="disabled" wire:target="toggleStatus({{ $link->id }})"
                                        class="inline-flex items-center gap-2 w-[6.5rem] text-[13px] font-semibold cursor-pointer {{ $active ? 'text-racun' : 'text-ink-soft' }}"
                                        title="{{ $active ? 'Nonaktifkan link' : 'Aktifkan link' }}">
                                    <span class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors {{ $active ? 'bg-racun' : 'bg-line-strong' }}">
                                        <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all {{ $active ? 'left-[18px]' : 'left-0.5' }}"></span>
                                    </span>
                                    {{ $active ? 'Aktif' : 'Nonaktif' }}
                                </button>

                                <div class="flex items-center">
                                    <button type="button" wire:click="$dispatch('open-caption-studio', { linkId: {{ $link->id }} })"
                                            class="icon-btn relative" aria-label="Caption AI"
                                            title="{{ $hasCaptions ? 'Caption AI: lihat caption tersimpan' : 'Caption AI: tulis caption untuk X & Threads' }}">
                                        <x-icon name="sparkle" />
                                        @if ($hasCaptions)
                                            <span class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-racun"></span>
                                        @endif
                                    </button>
                                    <button type="button" wire:click="openEditModal({{ $link->id }})" class="icon-btn" aria-label="Edit link" title="Edit"><x-icon name="edit" /></button>
                                    <button type="button" wire:click="confirmDelete({{ $link->id }})" class="icon-btn hover:!text-alert hover:!bg-alert-wash" aria-label="Hapus link" title="Hapus"><x-icon name="trash" /></button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($links->hasPages())
                    <div class="px-4 sm:px-5 py-3 border-t border-line">
                        {{ $links->links() }}
                    </div>
                @endif
            @elseif ($filtering)
                <div class="px-6 py-14 text-center">
                    <p class="font-semibold">Tidak ada link yang cocok.</p>
                    <p class="mt-1 text-sm text-ink-soft">Coba kata kunci lain atau tampilkan semua status.</p>
                    <button type="button" wire:click="clearFilters" class="btn btn-secondary mt-4">Hapus filter</button>
                </div>
            @else
                <div class="px-6 py-16 text-center max-w-md mx-auto">
                    <p class="text-lg font-bold">Belum ada link</p>
                    <p class="mt-1 text-ink-soft">Tempel link produk dari Shopee, Tokopedia, TikTok Shop, atau Lazada. Racunin membuatkan short link yang bisa kamu bagikan dan lacak kliknya.</p>
                    <div class="mt-5 flex flex-col sm:flex-row justify-center gap-2">
                        <button type="button" wire:click="openCreateModal" class="btn btn-primary"><x-icon name="plus" /> Buat link pertama</button>
                        <button type="button" wire:click="$dispatch('open-bulk-import')" class="btn btn-secondary">Impor banyak link</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Create / edit link ===== --}}
    @if ($showModal)
        <div class="modal-backdrop" wire:click.self="closeModal" @keydown.escape.window="$wire.closeModal()">
            <div class="modal-sheet animate-sheet sm:max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="link-form-title">
                <div class="flex items-center justify-between px-5 sm:px-6 h-14 border-b border-line shrink-0">
                    <h2 id="link-form-title" class="text-lg font-bold">{{ $isEditing ? 'Edit link' : 'Buat link' }}</h2>
                    <button type="button" wire:click="closeModal" class="icon-btn -mr-2" aria-label="Tutup"><x-icon name="x" class="size-5" /></button>
                </div>

                <form wire:submit="saveLink" class="flex-1 flex flex-col min-h-0">
                    <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5 space-y-8">

                        {{-- AI status / errors --}}
                        <div wire:loading.flex wire:target="aiBeautifyTitle,aiAutoCategorize,aiGenerateCaptions,autoExtractSingleUrl"
                             class="items-center gap-2.5 px-3 py-2.5 rounded-lg bg-racun-wash text-racun text-sm font-medium">
                            <x-icon name="refresh" class="animate-spin" />
                            <span wire:loading wire:target="autoExtractSingleUrl">Mengambil judul, foto, dan harga…</span>
                            <span wire:loading wire:target="aiBeautifyTitle">Merapikan judul dan deskripsi…</span>
                            <span wire:loading wire:target="aiAutoCategorize">Menentukan kategori dan label promo…</span>
                            <span wire:loading wire:target="aiGenerateCaptions">Menulis caption untuk X dan Threads…</span>
                        </div>
                        @if ($aiError)
                            <div class="flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-alert-wash text-alert text-sm">
                                <x-icon name="alert" class="mt-0.5" />
                                <p class="flex-1">{{ $aiError }}</p>
                                <button type="button" wire:click="$set('aiError', null)" class="shrink-0 cursor-pointer" aria-label="Tutup pesan"><x-icon name="x" /></button>
                            </div>
                        @endif

                        {{-- Product --}}
                        <section class="space-y-4">
                            <div>
                                <label for="original_url" class="field-label">Link produk</label>
                                <div class="flex gap-2">
                                    <input type="url" id="original_url" wire:model="original_url" required inputmode="url"
                                           placeholder="https://s.shopee.co.id/… atau https://vt.tokopedia.com/…" class="field"
                                           @unless ($isEditing)
                                               @paste="setTimeout(() => { $wire.original_url = $el.value; $wire.autoExtractSingleUrl() }, 0)"
                                           @endunless>
                                    <button type="button" wire:click="autoExtractSingleUrl" wire:loading.attr="disabled" wire:target="autoExtractSingleUrl" class="btn btn-secondary shrink-0">
                                        Ambil detail
                                    </button>
                                </div>
                                @error('original_url') <p class="field-error">{{ $message }}</p> @enderror
                                @if ($extractError) <p class="field-error">{{ $extractError }}</p> @endif
                                @unless ($isEditing)
                                    <p class="field-hint">Tempel link, judul, foto, dan harga terisi otomatis.</p>
                                @endunless
                            </div>

                            <div>
                                <div class="flex items-end justify-between gap-3 mb-1.5">
                                    <label for="title" class="field-label mb-0">Judul</label>
                                    <button type="button" wire:click="aiBeautifyTitle" wire:loading.attr="disabled" class="btn btn-sm btn-ghost -mr-2 text-racun">
                                        <x-icon name="sparkle" /> Rapikan dengan AI
                                    </button>
                                </div>
                                <input type="text" id="title" wire:model="title" required placeholder="Serum Niacinamide 10% Somethinc" class="field">
                                @error('title') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="slug" class="field-label">Short link</label>
                                <div class="flex items-stretch rounded-lg border border-line-strong bg-surface focus-within:border-racun focus-within:ring-3 focus-within:ring-racun/15 overflow-hidden">
                                    <span class="inline-flex items-center pl-3 text-sm text-ink-faint select-none whitespace-nowrap">{{ $shortDomain }}</span>
                                    <input type="text" id="slug" wire:model="slug" placeholder="serum-glowing" class="flex-1 min-w-0 h-10 pr-3 bg-transparent text-sm font-semibold outline-none">
                                </div>
                                @error('slug') <p class="field-error">{{ $message }}</p> @else <p class="field-hint">Kosongkan untuk dibuatkan otomatis. Huruf kecil, angka, dan tanda hubung.</p> @enderror
                            </div>

                            <div>
                                <label for="sub_id" class="field-label">Sub ID <span class="font-normal text-ink-soft">(opsional)</span></label>
                                <input type="text" id="sub_id" wire:model="sub_id" placeholder="serumglowing" class="field">
                                @error('sub_id') <p class="field-error">{{ $message }}</p> @else <p class="field-hint">Sub ID yang kamu isi saat membuat link di dashboard affiliate. Dipakai untuk mencocokkan pesanan di halaman Komisi. Terisi sendiri kalau ada di link.</p> @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div class="flex items-end justify-between gap-3 h-8 mb-1.5">
                                        <label for="category" class="field-label mb-0">Kategori</label>
                                        <button type="button" wire:click="aiAutoCategorize" wire:loading.attr="disabled" class="btn btn-sm btn-ghost -mr-2 text-racun" title="Isi kategori, label promo, dan slug dengan AI">
                                            <x-icon name="sparkle" /> Isi dengan AI
                                        </button>
                                    </div>
                                    <input type="text" id="category" wire:model="category" placeholder="Skincare" class="field">
                                </div>
                                <div>
                                    <div class="flex items-end h-8 mb-1.5"><label for="status" class="field-label mb-0">Status</label></div>
                                    <select id="status" wire:model="status" class="field">
                                        <option value="active">Aktif, bisa diklik</option>
                                        <option value="inactive">Nonaktif sementara</option>
                                        <option value="archived">Diarsipkan</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="description" class="field-label">Deskripsi <span class="font-normal text-ink-soft">(opsional)</span></label>
                                <textarea id="description" wire:model="description" rows="2" placeholder="Kenapa produk ini layak dibeli, dalam 1–2 kalimat." class="field"></textarea>
                            </div>
                        </section>

                        {{-- Share preview --}}
                        <section class="space-y-4">
                            <div>
                                <h3 class="font-bold">Tampilan saat dibagikan</h3>
                                <p class="text-sm text-ink-soft">Judul, teks, dan gambar yang muncul saat link dikirim di WhatsApp, Telegram, atau X.</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-[1fr_15rem]">
                                <div class="space-y-4">
                                    <div>
                                        <label for="og_title" class="field-label">Judul preview</label>
                                        <input type="text" id="og_title" wire:model.live.debounce.500ms="og_title" placeholder="Sama dengan judul produk" class="field">
                                    </div>
                                    <div>
                                        <label for="og_description" class="field-label">Teks preview</label>
                                        <textarea id="og_description" wire:model.live.debounce.500ms="og_description" rows="2" placeholder="Diskon 35% hari ini, original dari official store." class="field"></textarea>
                                    </div>
                                    <div>
                                        <label for="og_image_url" class="field-label">Link gambar</label>
                                        <input type="url" id="og_image_url" wire:model.live.debounce.500ms="og_image_url" placeholder="https://…" class="field">
                                        @error('og_image_url') <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                {{-- Chat bubble preview --}}
                                <div aria-hidden="true">
                                    <p class="text-[13px] text-ink-soft mb-1.5">Pratinjau</p>
                                    <div class="rounded-xl bg-[#DCF8C6] p-1.5">
                                        <div class="rounded-lg bg-white/80 overflow-hidden">
                                            @if ($og_image_url)
                                                <img src="{{ $og_image_url }}" alt="" class="w-full aspect-[1.91/1] object-cover bg-paper">
                                            @else
                                                <div class="w-full aspect-[1.91/1] bg-paper flex items-center justify-center text-ink-faint"><x-icon name="image" class="size-6" /></div>
                                            @endif
                                            <div class="px-2.5 py-2">
                                                <p class="text-[13px] font-semibold leading-snug line-clamp-2">{{ $og_title ?: ($title ?: 'Judul produk') }}</p>
                                                <p class="mt-0.5 text-xs text-ink-soft leading-snug line-clamp-2">{{ $og_description ?: ($description ?: 'Teks preview muncul di sini.') }}</p>
                                            </div>
                                        </div>
                                        <p class="px-1.5 pt-1.5 text-[13px] text-[#027EB5] truncate">{{ $shortDomain }}{{ $slug ?: 'slug-otomatis' }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- Catalog --}}
                        <section class="space-y-4">
                            <div>
                                <h3 class="font-bold">Katalog bio</h3>
                                <p class="text-sm text-ink-soft">Cara produk ini tampil di halaman katalog publik kamu.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="price" class="field-label">Harga</label>
                                    <div class="flex items-stretch rounded-lg border border-line-strong bg-surface focus-within:border-racun focus-within:ring-3 focus-within:ring-racun/15 overflow-hidden">
                                        <span class="inline-flex items-center pl-3 text-sm text-ink-soft select-none">Rp</span>
                                        <input type="number" id="price" wire:model="price" min="0" step="100" placeholder="119000" class="num flex-1 min-w-0 h-10 px-2 bg-transparent text-sm outline-none">
                                    </div>
                                    @error('price') <p class="field-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="discount_tag" class="field-label">Label promo</label>
                                    <input type="text" id="discount_tag" wire:model="discount_tag" placeholder="Diskon 35%" class="field">
                                </div>
                            </div>
                            <div class="divide-y divide-line rounded-lg border border-line">
                                <label class="flex items-center justify-between gap-4 px-4 py-3 cursor-pointer">
                                    <span>
                                        <span class="block text-sm font-semibold">Tampilkan di katalog</span>
                                        <span class="block text-[13px] text-ink-soft">Produk muncul di halaman link-in-bio kamu.</span>
                                    </span>
                                    <input type="checkbox" wire:model="is_in_catalog" class="size-5 accent-racun cursor-pointer">
                                </label>
                                <label class="flex items-center justify-between gap-4 px-4 py-3 cursor-pointer">
                                    <span>
                                        <span class="block text-sm font-semibold">Sematkan di atas</span>
                                        <span class="block text-[13px] text-ink-soft">Tampil paling atas di katalog.</span>
                                    </span>
                                    <input type="checkbox" wire:model="is_featured" class="size-5 accent-racun cursor-pointer">
                                </label>
                            </div>
                        </section>

                        {{-- Captions --}}
                        <section class="space-y-4" x-data="{ copied: null }">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-bold">Caption promosi</h3>
                                    <p class="text-sm text-ink-soft">Caption siap posting untuk X dan Threads, ikut tersimpan bersama link.</p>
                                </div>
                                <button type="button" wire:click="aiGenerateCaptions" wire:loading.attr="disabled" class="btn btn-sm btn-secondary shrink-0">
                                    <x-icon name="sparkle" /> {{ ! empty($generatedCaptions['twitter']) ? 'Tulis ulang' : 'Tulis caption' }}
                                </button>
                            </div>

                            @if (! empty($generatedCaptions['twitter']) || ! empty($generatedCaptions['threads']))
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach (['twitter' => 'X', 'threads' => 'Threads'] as $key => $platform)
                                        <div class="rounded-lg border border-line p-3 flex flex-col gap-3">
                                            <p class="text-[13px] font-semibold text-ink-soft">{{ $platform }}</p>
                                            <p class="flex-1 text-sm whitespace-pre-line leading-relaxed">{{ $generatedCaptions[$key] ?? '' }}</p>
                                            <button type="button" class="btn btn-sm btn-secondary self-start"
                                                    @click="navigator.clipboard.writeText(@js($generatedCaptions[$key] ?? '')); copied = '{{ $key }}'; setTimeout(() => copied = null, 1800)">
                                                <span x-text="copied === '{{ $key }}' ? 'Tersalin' : 'Salin caption {{ $platform }}'"></span>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 sm:px-6 py-3 border-t border-line bg-surface shrink-0">
                        <button type="button" wire:click="closeModal" class="btn btn-ghost">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveLink" class="btn btn-primary min-w-32">
                            <span wire:loading.remove wire:target="saveLink">{{ $isEditing ? 'Simpan perubahan' : 'Buat link' }}</span>
                            <span wire:loading wire:target="saveLink">Menyimpan…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Delete confirmation ===== --}}
    @if ($showDeleteModal)
        <div class="modal-backdrop" wire:click.self="cancelDelete" @keydown.escape.window="$wire.cancelDelete()">
            <div class="modal-sheet animate-sheet sm:max-w-md p-6" role="alertdialog" aria-modal="true" aria-labelledby="delete-title">
                <h2 id="delete-title" class="text-lg font-bold">Hapus link ini?</h2>
                <p class="mt-2 text-ink-soft">
                    @if ($deletingLink)
                        <span class="text-ink font-semibold">{{ $shortDomain }}{{ $deletingLink->slug }}</span> akan berhenti bekerja dan statistik kliknya ikut terhapus. Ini tidak bisa dibatalkan.
                    @else
                        Short link akan berhenti bekerja dan statistik kliknya ikut terhapus.
                    @endif
                </p>
                <p class="mt-2 text-sm text-ink-soft">Kalau hanya ingin berhenti sementara, nonaktifkan saja.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="btn btn-ghost">Batal</button>
                    <button type="button" wire:click="deleteLink" wire:loading.attr="disabled" wire:target="deleteLink" class="btn btn-danger">
                        <span wire:loading.remove wire:target="deleteLink">Hapus link</span>
                        <span wire:loading wire:target="deleteLink">Menghapus…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <livewire:dashboard.caption-studio />
    <livewire:dashboard.bulk-import />
</div>
