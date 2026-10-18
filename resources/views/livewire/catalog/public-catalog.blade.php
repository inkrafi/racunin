<div class="min-h-screen bg-paper" style="--accent: {{ $theme['accent'] }}; --wash: {{ $theme['wash'] }};" x-data="{ shared: false }">
    @php
        $socials = $catalog->social_links ?? [];
        $socialLinks = array_filter([
            'Instagram' => ! empty($socials['instagram']) ? 'https://instagram.com/' . ltrim($socials['instagram'], '@') : null,
            'TikTok' => ! empty($socials['tiktok']) ? 'https://tiktok.com/@' . ltrim($socials['tiktok'], '@') : null,
            'WhatsApp' => ! empty($socials['whatsapp']) ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $socials['whatsapp']) : null,
            'Telegram' => ! empty($socials['telegram']) ? 'https://t.me/' . ltrim($socials['telegram'], '@') : null,
            'YouTube' => ! empty($socials['youtube']) ? (str_starts_with($socials['youtube'], 'http') ? $socials['youtube'] : 'https://youtube.com/' . $socials['youtube']) : null,
        ]);
    @endphp

    {{-- ===== Profile ===== --}}
    <header style="background: var(--wash)">
        <div class="max-w-2xl mx-auto px-4 pt-10 pb-8 text-center">
            @if ($catalog->avatar_url)
                <img src="{{ $catalog->avatar_url }}" alt="{{ $catalog->display_name }}" class="size-20 rounded-full object-cover mx-auto border-[3px] border-white">
            @else
                <span class="size-20 rounded-full mx-auto inline-flex items-center justify-center text-2xl font-bold text-white" style="background: var(--accent)">
                    {{ mb_strtoupper(mb_substr($catalog->display_name, 0, 1)) }}
                </span>
            @endif
            <h1 class="mt-4 text-2xl font-extrabold tracking-tight">{{ $catalog->display_name }}</h1>
            <p class="text-sm text-ink-soft">{{ '@' . $catalog->username }}</p>
            @if ($catalog->bio)
                <p class="mt-3 max-w-md mx-auto leading-relaxed">{{ $catalog->bio }}</p>
            @endif

            <div class="mt-5 flex flex-wrap justify-center gap-2">
                @foreach ($socialLinks as $label => $href)
                    <a href="{{ $href }}" target="_blank" rel="noopener" class="btn btn-sm bg-surface border border-line hover:border-line-strong">{{ $label }}</a>
                @endforeach
                <button type="button" class="btn btn-sm bg-surface border border-line hover:border-line-strong"
                        @click="if (navigator.share) { navigator.share({ title: @js($catalog->display_name), url: location.href }) } else { navigator.clipboard.writeText(location.href); shared = true; setTimeout(() => shared = false, 1800) }">
                    <x-icon name="share" /> <span x-text="shared ? 'Link tersalin' : 'Bagikan'"></span>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">
        {{-- ===== Search & categories ===== --}}
        <label class="relative block">
            <span class="sr-only">Cari produk</span>
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari produk, misalnya &quot;serum untuk kulit berminyak&quot;" class="field h-11 pl-9">
        </label>

        @if (count($categories) > 0)
            <div class="mt-3 -mx-4 px-4 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Kategori">
                @foreach (array_merge(['all' => 'Semua (' . $totalProducts . ')'], array_combine($categories, $categories)) as $value => $label)
                    @php $selected = $category === $value; @endphp
                    <button type="button" wire:click="selectCategory(@js($value))" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                            @class([
                                'h-8 px-3.5 rounded-full text-[13px] font-semibold whitespace-nowrap border cursor-pointer',
                                'text-white' => $selected,
                                'bg-surface border-line text-ink-soft hover:text-ink' => ! $selected,
                            ])
                            @if ($selected) style="background: var(--accent); border-color: var(--accent)" @endif>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ===== Products ===== --}}
        <div class="mt-5">
            <div wire:loading.delay wire:target="search, selectCategory" class="grid grid-cols-2 gap-3">
                @foreach (range(1, 4) as $i)
                    <div class="space-y-2"><div class="skeleton aspect-square"></div><div class="skeleton h-4 w-3/4"></div><div class="skeleton h-4 w-1/3"></div></div>
                @endforeach
            </div>

            <div wire:loading.remove.delay wire:target="search, selectCategory">
                @if ($products->count() > 0)
                    <ul class="grid grid-cols-2 gap-x-3 gap-y-6">
                        @foreach ($products as $product)
                            <li wire:key="product-{{ $product->id }}" class="flex flex-col">
                                <a href="{{ $product->short_url }}" target="_blank" rel="noopener sponsored" class="group flex flex-col flex-1">
                                    <div class="relative aspect-square rounded-xl overflow-hidden bg-surface border border-line">
                                        @if ($product->og_image_url)
                                            <img src="{{ $product->og_image_url }}" alt="{{ $product->title }}" loading="lazy" class="size-full object-cover group-hover:scale-[1.03] transition-transform duration-300">
                                        @else
                                            <span class="size-full flex items-center justify-center text-ink-faint"><x-icon name="bag" class="size-8" /></span>
                                        @endif
                                        @if ($product->discount_tag)
                                            <span class="absolute left-2 top-2 px-2 py-0.5 rounded-md text-xs font-bold bg-white" style="color: var(--accent)">{{ $product->discount_tag }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-2.5 text-sm font-semibold leading-snug line-clamp-2">{{ $product->title }}</p>
                                    @if ($product->price)
                                        <p class="mt-1 font-extrabold num">{{ $product->formatted_price }}</p>
                                    @endif
                                    <span class="mt-auto pt-3">
                                        <span class="btn btn-sm w-full text-white" style="background: var(--accent)">{{ $product->buy_label }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($hasMore)
                        <div class="mt-8 text-center">
                            <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" class="btn btn-secondary">
                                <span wire:loading.remove wire:target="loadMore">Muat lebih banyak produk</span>
                                <span wire:loading wire:target="loadMore">Memuat…</span>
                            </button>
                        </div>
                    @endif
                @else
                    <div class="py-14 text-center">
                        @if ($search !== '' || $category !== 'all')
                            <p class="font-semibold">Produk tidak ditemukan</p>
                            <p class="mt-1 text-sm text-ink-soft">Coba kata lain atau lihat semua kategori.</p>
                            @if ($search !== '')
                                <button type="button" wire:click="$set('search', '')" class="btn btn-secondary mt-4">Hapus pencarian</button>
                            @else
                                <button type="button" wire:click="selectCategory('all')" class="btn btn-secondary mt-4">Lihat semua kategori</button>
                            @endif
                        @else
                            <p class="font-semibold">Belum ada produk di katalog ini</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </main>

    <footer class="max-w-2xl mx-auto px-4 pb-10 pt-4 text-center text-[13px] text-ink-soft space-y-1">
        <p>Link di halaman ini adalah link affiliate. Harga mengikuti toko saat produk dibuka.</p>
        <p>Dibuat dengan <a href="{{ url('/') }}" class="font-bold text-ink">racunin<span class="text-racun">/</span></a></p>
    </footer>
</div>
