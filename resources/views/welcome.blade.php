@php
    // Demo domain for the hero: the real host in production, a readable stand-in locally
    $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'racunin.id';
    $demoDomain = in_array($host, ['localhost', '127.0.0.1'], true) ? 'racunin.id' : $host;

    $platforms = [...app(\App\Support\AffiliateUrlPolicy::class)->platformLabels(), 'Involve Asia', 'ACCESSTRADE'];

    // Hero bubbles: left %, size px, rise seconds, delay seconds (negative = already rising on load), style
    $bubbles = [
        [6, 18, 13, -2, 'lime'], [14, 9, 9, -6, 'pink'], [23, 26, 17, -11, 'ring'], [34, 12, 11, -3, 'lime'],
        [47, 8, 8, -1, 'white'], [58, 20, 15, -9, 'pink'], [66, 11, 10, -4, 'ring'], [74, 30, 19, -14, 'lime'],
        [83, 10, 9, -7, 'white'], [91, 16, 12, -5, 'pink'], [96, 7, 7, -2, 'lime'],
    ];
@endphp

<x-layouts.app
    title="Racunin: short link, katalog bio & tracking komisi untuk affiliate"
    ogTitle="Racunin: racunin followers, panen komisinya"
    ogDescription="Short link dengan namamu sendiri, statistik klik per sumber, tracking komisi per link, dan katalog link-in-bio untuk affiliate Shopee, Tokopedia, TikTok Shop, dan lainnya. Gratis."
>
    @push('head')
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500..700&display=swap" rel="stylesheet">
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Racunin',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'description' => 'Short link, katalog bio, statistik klik, dan tracking komisi untuk affiliate marketplace di Indonesia.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'IDR'],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
    <style>
        /* Landing headlines: wide and confident, but kept at semibold so long lines stay light */
        .font-headline { font-family: 'Unbounded', var(--font-display); font-weight: 600; letter-spacing: -0.035em; text-wrap: balance; }

        /* ---- Hero: a plum potion, bubbles rising, a pink glow that follows the pointer ---- */
        .hero-ramuan { position: relative; overflow: hidden; isolation: isolate;
            background: radial-gradient(90% 80% at 100% 0%, var(--color-plum-soft) 0%, var(--color-plum) 62%); }
        .hero-glow { position: absolute; inset: 0; z-index: -1; pointer-events: none; transition: opacity .4s;
            background: radial-gradient(520px circle at var(--mx, 78%) var(--my, 22%), rgba(255, 61, 139, 0.32), transparent 62%); }
        .bubbles { position: absolute; inset: 0; z-index: -1; pointer-events: none; }
        .bubble { position: absolute; bottom: -40px; border-radius: 50%; opacity: 0;
            animation: rise var(--d) linear var(--delay) infinite; }
        .bubble.lime { background: radial-gradient(circle at 32% 30%, #F2FFC4 0 18%, var(--color-lime) 46%); }
        .bubble.pink { background: radial-gradient(circle at 32% 30%, #FFC2DA 0 18%, #FF3D8B 50%); }
        .bubble.white { background: rgba(255, 255, 255, 0.75); }
        .bubble.ring { border: 2px solid rgba(198, 244, 50, 0.7); }
        @keyframes rise {
            0% { transform: translate(0, 0) scale(.6); opacity: 0; }
            12% { opacity: .9; }
            50% { transform: translate(22px, -460px) scale(1); }
            88% { opacity: .85; }
            100% { transform: translate(-12px, -940px) scale(1.1); opacity: 0; }
        }

        /* Headline words slide up one by one, once */
        .hw { display: inline-block; overflow: hidden; vertical-align: bottom; padding: 0 .04em .08em 0; margin-bottom: -.08em; }
        .hw > span { display: inline-block; transform: translateY(110%); animation: wordUp .9s cubic-bezier(.16, 1, .3, 1) forwards; animation-delay: calc(var(--i) * 110ms + 120ms); }
        @keyframes wordUp { to { transform: translateY(0); } }
        .fade-up { opacity: 0; animation: fadeUp .8s cubic-bezier(.16, 1, .3, 1) forwards; animation-delay: var(--delay, .6s); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        /* Demo card floats, and tilts toward the pointer on desktop */
        .floaty { animation: floaty 7s ease-in-out infinite; }
        @keyframes floaty { 50% { transform: translateY(-10px); } }
        .tilt { transform: perspective(1000px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg)); transition: transform .25s ease-out; }

        /* The marker stroke sweeps across the new short link */
        .marker-sweep { background-repeat: no-repeat; background-size: 0% 100%, 0% 100%; transition: background-size 0.7s cubic-bezier(0.65, 0, 0.35, 1) 0.15s; }
        .marker-sweep.is-on { background-size: 100% 100%, 100% 100%; }

        /* Scroll reveal: only armed by JS, so the page is complete without it */
        .reveal-on .reveal { opacity: 0; transform: translateY(28px); transition: opacity .8s cubic-bezier(.16, 1, .3, 1), transform .8s cubic-bezier(.16, 1, .3, 1); transition-delay: var(--delay, 0s); }
        .reveal-on .reveal.in { opacity: 1; transform: none; }
        .reveal-on .reveal .grow { transform-origin: left; transform: scaleX(0); transition: transform 1.1s cubic-bezier(.16, 1, .3, 1) .25s; }
        .reveal-on .reveal .grow-y { transform-origin: bottom; transform: scaleY(0); transition: transform .9s cubic-bezier(.16, 1, .3, 1); transition-delay: calc(var(--i, 0) * 70ms + .2s); }
        .reveal-on .reveal.in .grow, .reveal-on .reveal.in .grow-y { transform: none; }
        .reveal-on .reveal .pop { opacity: 0; transform: translateY(12px) scale(.96); transition: opacity .5s ease-out, transform .6s cubic-bezier(.34, 1.56, .64, 1); transition-delay: var(--pop, .3s); }
        .reveal-on .reveal.in .pop { opacity: 1; transform: none; }

        /* Stickers around the catalog phone bob gently */
        .bob { animation: bob 5s ease-in-out infinite; animation-delay: var(--delay, 0s); }
        @keyframes bob { 50% { transform: translateY(-8px); } }

        .typing span { animation: blink 1.2s infinite; }
        .typing span:nth-child(2) { animation-delay: .2s; }
        .typing span:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
    </style>
    @endpush

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-plum/90 backdrop-blur text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <x-logo dark />
            <nav class="flex items-center gap-1">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Buka dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn text-plum-ink hover:text-white hover:bg-white/10">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Daftar gratis</a>
                @endauth
            </nav>
        </div>
    </header>

    {{-- ===== Hero ===== --}}
    <section class="hero-ramuan text-white" data-hero>
        <div class="hero-glow" aria-hidden="true"></div>
        <div class="bubbles" aria-hidden="true">
            @foreach ($bubbles as [$left, $size, $duration, $delay, $style])
                <span class="bubble {{ $style }}" style="left: {{ $left }}%; width: {{ $size }}px; height: {{ $size }}px; --d: {{ $duration }}s; --delay: {{ $delay }}s"></span>
            @endforeach
        </div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-14 pb-20 sm:pt-20 sm:pb-28 grid gap-14 lg:grid-cols-[1.2fr_1fr] lg:items-center">
            <div>
                <h1 class="font-headline text-[44px] leading-[1.02] sm:text-[68px] lg:text-[78px]">
                    @foreach (['Racunin', 'followers,', 'panen', 'komisinya.'] as $i => $word)
                        <span class="hw"><span style="--i: {{ $i }}">{{ $word }}</span></span>
                    @endforeach
                </h1>
                <p class="fade-up mt-7 text-lg sm:text-xl text-plum-ink leading-relaxed max-w-[46ch]" style="--delay: .65s">
                    Link affiliate yang panjang jadi link pendek dengan namamu. Kamu lihat konten mana yang bikin orang klik dan link mana yang menghasilkan komisi. Semua produkmu tampil di satu katalog untuk bio.
                </p>
                <div class="fade-up mt-9 flex flex-col sm:flex-row gap-3" style="--delay: .8s">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary h-13 px-7 text-base">Buka dashboard</a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-primary h-13 px-7 text-base">Bikin link pertamamu, gratis</a>
                    @endauth
                    <a href="#cara-pakai" class="btn h-13 px-7 text-base text-white border border-white/30 hover:bg-white/10">Lihat cara kerjanya</a>
                </div>
                <p class="fade-up mt-6 text-sm text-plum-ink max-w-[52ch]" style="--delay: .95s">Bisa untuk link {{ implode(', ', $platforms) }}.</p>
            </div>

            <div x-data="{ done: false }"
                 x-init="
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { done = true; return; }
                    setTimeout(() => done = true, 1700);
                 "
                 class="fade-up relative pt-10 pb-12 sm:px-6 lg:px-0" style="--delay: .5s"
                 aria-label="Contoh: link affiliate panjang diubah menjadi short link">

                {{-- Catalog peeking out behind the card --}}
                <div class="absolute right-0 top-0 w-[58%] max-w-[260px] rotate-[7deg] rounded-[26px] bg-plum-soft/80 border border-white/15 p-3 shadow-[0_30px_60px_-20px_rgba(0,0,0,0.5)]" aria-hidden="true">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['serum', 'sunscreen', 'toner', 'lip'] as $image)
                            <img src="{{ asset('images/demo/' . $image . '.svg') }}" alt="" class="aspect-square w-full rounded-xl">
                        @endforeach
                    </div>
                    <p class="mt-2.5 px-1 text-xs font-semibold text-plum-ink">{{ $demoDomain }}/@rinaracun</p>
                </div>

                <div class="floaty relative">
                    <div data-tilt class="tilt rounded-3xl bg-surface text-ink p-5 sm:p-6 shadow-[0_40px_80px_-24px_rgba(0,0,0,0.6)] lg:mr-10">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/demo/serum.svg') }}" alt="" class="size-14 rounded-xl shrink-0">
                            <div class="min-w-0">
                                <p class="font-bold leading-snug truncate">Serum Niacinamide 10%</p>
                                <p class="text-sm text-ink-soft"><span class="font-bold text-ink num">Rp 119.000</span> di Shopee</p>
                            </div>
                        </div>

                        <p class="mt-5 text-[13px] font-semibold text-ink-soft">Link affiliate dari Shopee</p>
                        <p class="mt-1.5 font-medium break-all leading-relaxed transition-all duration-500"
                           :class="done ? 'text-ink-faint line-through decoration-ink-faint/60 text-[12px]' : 'text-ink text-sm'">
                            https://shopee.co.id/Somethinc-Niacinamide-Moisture-Sabi-Beet-Maxi-Ampoule-i.187313328.6101928372?sp_atk=2f6a1e0b-5c8d-4e7a&amp;xptdk=2f6a1e0b-5c8d-4e7a-9b1d-7c3e
                        </p>

                        <div class="mt-5 pt-5 border-t border-line transition-opacity duration-500" :class="done ? 'opacity-100' : 'opacity-0'">
                            <p class="text-[13px] font-semibold text-ink-soft">Jadi link kamu</p>
                            <p class="mt-1.5 text-2xl sm:text-[26px] font-bold tracking-tight">
                                <span class="marker marker-sweep" :class="done && 'is-on'"><span class="text-ink-soft font-semibold">{{ $demoDomain }}/</span>serum-glowing</span>
                            </p>

                            <dl class="mt-5 grid grid-cols-3 gap-2.5 text-sm">
                                @foreach ([['TikTok', 412], ['Instagram', 268], ['WhatsApp', 97]] as [$source, $clicks])
                                    <div class="rounded-xl bg-paper border border-line p-3">
                                        <dt class="text-ink-soft text-[13px]">{{ $source }}</dt>
                                        <dd class="mt-0.5 text-lg font-extrabold num" data-count-hero="{{ $clicks }}">{{ $clicks }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                            <p class="mt-2 text-[13px] text-ink-soft">Contoh klik 7 hari terakhir.</p>
                        </div>
                    </div>
                </div>

                {{-- A commission lands after the link is made --}}
                <div class="absolute right-0 bottom-0 sm:right-4 lg:-right-2 z-10 flex items-center gap-3 rounded-2xl bg-plum border border-white/15 pl-3 pr-4 py-2.5 shadow-[0_20px_40px_-12px_rgba(0,0,0,0.6)] transition-all duration-700 delay-[900ms]"
                     :class="done ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'" aria-hidden="true">
                    <span class="size-9 rounded-full bg-lime text-plum inline-flex items-center justify-center shrink-0"><x-icon name="coin" class="size-5" /></span>
                    <span class="leading-tight">
                        <span class="block text-xs text-plum-ink">Komisi dari /serum-glowing</span>
                        <span class="block font-extrabold text-white num">+Rp 24.500</span>
                    </span>
                </div>

                <span class="bob absolute left-0 top-4 sm:left-4 lg:-left-6 z-10 -rotate-6 rounded-full bg-lime text-plum px-4 py-2 text-sm font-extrabold shadow-lg" aria-hidden="true">Kena racun!</span>
            </div>
        </div>
    </section>

    {{-- ===== What you get ===== --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-24 sm:py-32 space-y-28 sm:space-y-36">
        {{-- Share preview --}}
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal max-w-lg">
                <h2 class="font-headline text-[36px] leading-[1.04] sm:text-[60px]">Dibagikan di grup, kelihatan meyakinkan.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Judul, deskripsi, dan foto yang muncul saat link dikirim ke WhatsApp, Telegram, atau X bisa kamu atur sendiri. Yang menerima langsung tahu produk apa yang kamu kirim, bukan cuma melihat deretan huruf acak.</p>
            </div>

            <div class="reveal max-w-sm w-full lg:justify-self-center" style="--delay: .1s" aria-hidden="true">
                <div class="rounded-[28px] bg-[#EFE7DE] p-3 shadow-[0_30px_60px_-30px_rgba(43,11,63,0.45)]">
                    <div class="flex items-center gap-3 px-2 pb-3">
                        <span class="size-9 rounded-full bg-racun text-white text-sm font-bold inline-flex items-center justify-center">RS</span>
                        <div class="leading-tight">
                            <p class="text-sm font-bold">Racun Skincare</p>
                            <p class="text-xs text-ink-soft">248 anggota</p>
                        </div>
                    </div>

                    <div class="pop max-w-[80%] rounded-2xl rounded-tl-md bg-white px-3 py-2 text-sm" style="--pop: .25s">
                        <p class="text-xs font-bold text-plum-soft">Mila</p>
                        <p>Kak, serum yang di TikTok kemarin link-nya mana?</p>
                    </div>

                    <div class="pop mt-2 ml-auto max-w-[88%] rounded-2xl rounded-tr-md bg-[#DCF8C6] p-1.5" style="--pop: .75s">
                        <div class="rounded-xl bg-white/80 overflow-hidden">
                            <img src="{{ asset('images/demo/serum-share.svg') }}" alt="" class="w-full aspect-[1.91/1] object-cover" loading="lazy">
                            <div class="px-3 py-2.5">
                                <p class="text-sm font-semibold leading-snug">Diskon 35% Serum Niacinamide 10%, original dari official store</p>
                                <p class="mt-0.5 text-[13px] text-ink-soft">Bantu samarkan bekas jerawat. Cocok untuk kulit berminyak.</p>
                                <p class="mt-1 text-xs text-ink-faint">{{ $demoDomain }}</p>
                            </div>
                        </div>
                        <p class="px-1.5 pt-1.5 text-sm text-[#027EB5]">{{ $demoDomain }}/serum-glowing</p>
                        <p class="px-1.5 pb-0.5 text-right text-[11px] text-ink-soft">19.42 <span class="text-[#34B7F1]">✓✓</span></p>
                    </div>

                    <div class="pop mt-2 inline-flex items-center gap-1 rounded-2xl bg-white px-3 py-2.5 typing" style="--pop: 1.4s">
                        <span class="size-1.5 rounded-full bg-ink-soft"></span><span class="size-1.5 rounded-full bg-ink-soft"></span><span class="size-1.5 rounded-full bg-ink-soft"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Analytics --}}
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal max-w-lg lg:order-2">
                <h2 class="font-headline text-[36px] leading-[1.04] sm:text-[60px]">Tahu konten mana yang bikin orang klik.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Klik dihitung per hari, per link, dan per sumber: TikTok, Instagram, WhatsApp, atau dibuka langsung. Bot dan crawler preview tidak ikut dihitung.</p>
            </div>
            <div class="reveal panel p-6 max-w-md w-full lg:justify-self-center shadow-[0_30px_60px_-30px_rgba(43,11,63,0.35)]" style="--delay: .1s" aria-hidden="true">
                <p class="text-sm text-ink-soft">Semua link, 7 hari terakhir</p>
                <p class="mt-1 text-[44px] leading-none font-extrabold tracking-tight num"><span data-count="2354">2.354</span> <span class="text-xl font-bold text-ink-soft">klik</span></p>

                <div class="mt-5 flex items-end gap-2 h-20">
                    @foreach ([38, 52, 44, 70, 61, 88, 100] as $i => $height)
                        <div class="grow-y flex-1 rounded-t-md {{ $i === 6 ? 'bg-racun' : 'bg-racun-wash' }}" style="height: {{ $height }}%; --i: {{ $i }}"></div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex justify-between text-[11px] text-ink-faint"><span>Sen</span><span>Hari ini</span></div>

                <ul class="mt-6 space-y-3">
                    @foreach ([['TikTok', 45], ['Instagram', 30], ['WhatsApp', 15], ['Langsung', 10]] as [$label, $pct])
                        <li>
                            <div class="flex justify-between text-sm"><span class="font-semibold">{{ $label }}</span><span class="text-ink-soft num">{{ $pct }}%</span></div>
                            <div class="mt-1.5 h-2 rounded-full bg-paper overflow-hidden"><div class="grow h-full rounded-full bg-plum" style="width: {{ $pct }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Commissions --}}
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal max-w-lg">
                <h2 class="font-headline text-[36px] leading-[1.04] sm:text-[60px]">Klik itu bagus. Komisi lebih bagus.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Impor laporan komisi (CSV) dari dashboard affiliate. Racunin mencocokkan pesanan ke link kamu lewat Sub ID atau ID produk, lalu menunjukkan komisi per link dan komisi per klik.</p>
            </div>
            <div class="reveal panel p-6 max-w-md w-full lg:justify-self-center shadow-[0_30px_60px_-30px_rgba(43,11,63,0.35)]" style="--delay: .1s" aria-hidden="true">
                <p class="text-sm text-ink-soft">Komisi 30 hari terakhir</p>
                <p class="mt-1 text-[40px] leading-none font-extrabold tracking-tight num"><span class="marker">Rp <span data-count="1284500">1.284.500</span></span></p>
                <div class="mt-4 flex flex-wrap gap-2 text-[13px] font-semibold">
                    <span class="rounded-full bg-paper px-3 py-1">23 pesanan</span>
                    <span class="rounded-full bg-paper px-3 py-1">Rp 3.118 per klik</span>
                </div>
                <ul class="mt-6 divide-y divide-line">
                    @foreach ([['serum-glowing', 'Rp 642.000', 100], ['sunscreen-50', 'Rp 318.200', 50], ['lip-serum', 'Rp 124.300', 19]] as [$slug, $amount, $pct])
                        <li class="py-3">
                            <div class="flex justify-between gap-3 text-sm"><span class="font-semibold">/{{ $slug }}</span><span class="font-bold num">{{ $amount }}</span></div>
                            <div class="mt-2 h-1.5 rounded-full bg-paper overflow-hidden"><div class="grow h-full rounded-full bg-racun" style="width: {{ $pct }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Catalog --}}
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal max-w-lg lg:order-2">
                <h2 class="font-headline text-[36px] leading-[1.04] sm:text-[60px]">Satu link di bio, semua racun ada di situ.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Produk rekomendasimu tersusun dengan foto, harga, kategori, dan kolom pencarian. Followers tinggal cari, tap, lalu checkout di tokonya.</p>
            </div>

            <div class="reveal relative max-w-xs w-full mx-auto lg:justify-self-center" style="--delay: .1s" aria-hidden="true">
                <span class="bob absolute -left-6 sm:-left-14 top-16 z-10 rotate-[-6deg] rounded-full bg-lime text-plum px-4 py-2 text-sm font-extrabold shadow-lg">+128 klik hari ini</span>
                <span class="bob absolute -right-4 sm:-right-12 bottom-24 z-10 rotate-[5deg] rounded-full bg-racun text-white px-4 py-2 text-sm font-extrabold shadow-lg" style="--delay: 1.2s">Checkout!</span>

                <div class="rounded-[34px] border-[7px] border-plum bg-paper overflow-hidden shadow-[0_40px_80px_-30px_rgba(43,11,63,0.55)]">
                    <div class="bg-racun-wash px-4 pt-7 pb-4 text-center">
                        <span class="size-14 rounded-full mx-auto inline-flex items-center justify-center text-xl font-bold text-white bg-racun">R</span>
                        <p class="mt-2 font-display text-lg font-extrabold">Racun Skincare Rina</p>
                        <p class="text-xs text-ink-soft">@rinaracun</p>
                    </div>
                    <div class="p-3 grid grid-cols-2 gap-2.5">
                        @foreach ([['serum', 'Serum Niacinamide 10%', 'Rp 119.000'], ['sunscreen', 'Sunscreen SPF 50', 'Rp 89.000'], ['toner', 'Toner AHA BHA', 'Rp 75.000'], ['lip', 'Lip Serum', 'Rp 49.000']] as $i => [$image, $name, $price])
                            <div class="pop rounded-xl bg-surface p-1.5 transition-transform duration-300 hover:-translate-y-1" style="--pop: {{ .3 + $i * .12 }}s">
                                <img src="{{ asset('images/demo/' . $image . '.svg') }}" alt="" class="aspect-square w-full rounded-lg" loading="lazy">
                                <p class="mt-1.5 text-[11px] font-semibold leading-tight line-clamp-1">{{ $name }}</p>
                                <p class="text-[12px] font-extrabold num">{{ $price }}</p>
                                <span class="mt-1.5 block rounded-full bg-racun py-1 text-center text-[10px] font-bold text-white">Beli</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== How it works (a real sequence) ===== --}}
    <section id="cara-pakai" class="bg-plum text-white scroll-mt-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-24 sm:py-28">
            <h2 class="reveal font-headline text-[36px] leading-[1.04] sm:text-[60px]">Cara kerjanya, tiga langkah.</h2>
            <ol class="mt-14 grid gap-12 md:grid-cols-3">
                @foreach ([
                    ['Tempel link affiliate', 'Dari Shopee, Tokopedia, TikTok Shop, Lazada, dan toko lainnya. Judul, foto, dan harga terisi otomatis kalau tokonya mengizinkan.'],
                    ['Kasih nama yang gampang diingat', 'Ganti kode acak jadi /serum-glowing, supaya gampang diketik dan disebut di video.'],
                    ['Sebar, lalu pantau', 'Pasang di caption, story, atau grup. Klik tercatat beserta sumbernya.'],
                ] as $index => [$title, $text])
                    <li class="reveal max-w-sm" style="--delay: {{ $index * .12 }}s">
                        <span class="font-headline text-6xl text-lime num">{{ $index + 1 }}</span>
                        <h3 class="mt-4 text-xl font-bold">{{ $title }}</h3>
                        <p class="mt-2 text-plum-ink leading-relaxed">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ===== AI helpers ===== --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-24 sm:py-28 grid gap-12 lg:grid-cols-[1fr_1.3fr]">
        <div class="reveal">
            <h2 class="font-headline text-[36px] leading-[1.04] sm:text-[60px]">Biar AI yang mikirin caption.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Pakai Google Gemini. Semua hasilnya bisa kamu edit dulu sebelum dipakai.</p>
        </div>
        <dl class="grid gap-x-10 gap-y-10 sm:grid-cols-2">
            @foreach ([
                ['Caption siap posting', 'Dua versi untuk X dan Threads, short link kamu sudah terpasang.'],
                ['Judul jadi enak dibaca', 'Judul marketplace yang penuh "COD ORI READY STOK" diringkas jadi jelas.'],
                ['Cek ide produk', 'Skor keunikan dan daya tarik emosional produk, plus sudut promosi yang bisa kamu coba.'],
                ['Saran jam posting', 'Dibaca dari pola klik akunmu sendiri, bukan tips umum.'],
            ] as $index => [$term, $desc])
                <div class="reveal border-t-2 border-plum pt-5" style="--delay: {{ $index * .08 }}s">
                    <dt class="text-lg font-bold">{{ $term }}</dt>
                    <dd class="mt-1.5 text-ink-soft leading-relaxed">{{ $desc }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ===== Closing CTA ===== --}}
    <section class="hero-ramuan text-white" style="background: var(--color-racun)">
        <div class="bubbles" aria-hidden="true">
            @foreach (array_slice($bubbles, 0, 7) as [$left, $size, $duration, $delay, $style])
                <span class="bubble {{ $style === 'pink' ? 'white' : $style }}" style="left: {{ $left + 3 }}%; width: {{ $size }}px; height: {{ $size }}px; --d: {{ $duration }}s; --delay: {{ $delay }}s"></span>
            @endforeach
        </div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 sm:py-28 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="reveal">
                <h2 class="font-headline text-[36px] leading-[1.04] sm:text-[60px] max-w-[14ch]">Racun pertamamu tinggal satu link lagi.</h2>
                <p class="mt-5 text-lg text-white/85">Gratis. Daftar pakai Google atau email, tanpa kartu kredit.</p>
            </div>
            @auth
                <a href="{{ route('dashboard') }}" class="reveal btn h-14 px-8 text-lg bg-white text-racun hover:bg-lime hover:text-plum self-start lg:self-auto">Buka dashboard</a>
            @else
                <a href="{{ route('register') }}" class="reveal btn h-14 px-8 text-lg bg-white text-racun hover:bg-lime hover:text-plum self-start lg:self-auto">Daftar gratis</a>
            @endauth
        </div>
    </section>

    <footer class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between text-sm text-ink-soft">
        <x-logo class="text-lg" />
        <nav class="flex gap-5">
            <a href="{{ route('login') }}" class="hover:text-ink">Masuk</a>
            <a href="{{ route('register') }}" class="hover:text-ink">Daftar</a>
        </nav>
        <p>© {{ date('Y') }} Racunin. Tidak berafiliasi dengan marketplace mana pun.</p>
    </footer>

    @push('scripts')
    <script>
        (() => {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const fmt = (n) => n.toLocaleString('id-ID');

            const countUp = (el, target) => {
                if (reduce) { el.textContent = fmt(target); return; }
                const start = performance.now();
                const step = (now) => {
                    const p = Math.min(1, (now - start) / 1200);
                    el.textContent = fmt(Math.round(target * (1 - Math.pow(1 - p, 3))));
                    if (p < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            };

            // Hero click numbers count up as the short link appears
            setTimeout(() => document.querySelectorAll('[data-count-hero]').forEach((el) => countUp(el, +el.dataset.countHero)), reduce ? 0 : 1800);

            // Sections rise in as they scroll into view; numbers inside them count up
            const reveals = document.querySelectorAll('.reveal');
            if (!reduce && 'IntersectionObserver' in window) {
                document.documentElement.classList.add('reveal-on');
                const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('in');
                    entry.target.querySelectorAll('[data-count]').forEach((el) => countUp(el, +el.dataset.count));
                    io.unobserve(entry.target);
                }), { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });
                reveals.forEach((el) => io.observe(el));
            }

            if (reduce || !window.matchMedia('(pointer: fine)').matches) return;

            // Pink glow follows the pointer across the hero
            const hero = document.querySelector('[data-hero]');
            let frame = null;
            hero?.addEventListener('pointermove', (e) => {
                if (frame) return;
                frame = requestAnimationFrame(() => {
                    const r = hero.getBoundingClientRect();
                    hero.style.setProperty('--mx', `${e.clientX - r.left}px`);
                    hero.style.setProperty('--my', `${e.clientY - r.top}px`);
                    frame = null;
                });
            });

            // Demo card leans toward the pointer
            const card = document.querySelector('[data-tilt]');
            card?.addEventListener('pointermove', (e) => {
                const r = card.getBoundingClientRect();
                card.style.setProperty('--ry', `${((e.clientX - r.left) / r.width - 0.5) * 8}deg`);
                card.style.setProperty('--rx', `${((e.clientY - r.top) / r.height - 0.5) * -8}deg`);
            });
            card?.addEventListener('pointerleave', () => { card.style.setProperty('--rx', '0deg'); card.style.setProperty('--ry', '0deg'); });
        })();
    </script>
    @endpush
</x-layouts.app>
