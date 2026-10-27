@php
    use App\Support\LandingPageContent;

    $theme = $page['theme'] ?? 'racun';
    $cta = $page['cta_text'] ?: $link->buy_label;
    $ctaUrl = $link->landing_page_cta_url;
    $viaChat = $page['cta_url'] && preg_match('#(wa\.me|whatsapp\.com)#i', $page['cta_url']);
    $gallery = array_values(array_filter(array_merge([$link->og_image_url], $page['images'] ?? [])));
    $secondsLeft = LandingPageContent::promoSecondsLeft($page);
    $percent = LandingPageContent::discountPercent($page, $link);
    $original = $percent ? 'Rp ' . number_format($page['original_price'], 0, ',', '.') : null;
    $saving = $percent ? 'Rp ' . number_format($page['original_price'] - (float) $link->price, 0, ',', '.') : null;
    $video = LandingPageContent::video($page['video_url'] ?? null, $page['video_shape'] ?? 'auto');
    $store = $link->platform_label;
    $comparison = $page['comparison'] ?? ['without' => [], 'with' => []];
    $fonts = [
        'racun' => 'Bricolage+Grotesque:opsz,wght@12..96,700..800',
        'glow' => 'Fraunces:opsz,wght@9..144,500..700',
        'gadget' => 'Space+Grotesk:wght@500..700',
        'dapur' => 'Lilita+One',
        'belajar' => 'Newsreader:opsz,wght@6..72,500..700',
    ];
    $icon = [
        'arrow' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>',
        'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 0 1-11.6 7.1L4 20l1-4.2A8 8 0 1 1 20 12Z"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 7l10 10"/><path d="M17 7 7 17"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
        'play' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
    ];
    $button = fn (string $extra = '') => '<a href="' . e($ctaUrl) . '" rel="nofollow sponsored" class="lp-btn ' . $extra . '">' . ($viaChat ? $icon['chat'] : '') . '<span>' . e($cta) . '</span>' . ($viaChat ? '' : $icon['arrow']) . '</a>';
@endphp
<x-layouts.app :title="$page['headline']" :og-title="$page['headline']" :og-description="$page['subheadline'] ?: $link->effective_og_description" :og-image="$gallery[0] ?? null">
    @push('head')
        <link href="https://fonts.googleapis.com/css2?family={{ $fonts[$theme] ?? $fonts['racun'] }}&display=swap" rel="stylesheet">
        @if ($gallery)
            <link rel="preload" as="image" href="{{ $gallery[0] }}">
        @endif
        @if ($preview)
            <meta name="robots" content="noindex">
        @endif
        <style>
            .lp { --paper:#F7F0FA; --surface:#FFFFFF; --ink:#22092F; --soft:#6D5A78; --line:#E7DCEF; --accent:#E0186C; --accent-deep:#B30F53; --on-accent:#FFFFFF; --tag:#C6F432; --on-tag:#22092F; --hot:#E0186C; --display:'Bricolage Grotesque', sans-serif; --display-weight:800; --display-track:-0.025em; }
            .lp[data-theme="glow"] { --paper:#FCEEEE; --surface:#FFFAF9; --ink:#3B1020; --soft:#7E5563; --line:#F1D6DA; --accent:#C2255C; --accent-deep:#9A1846; --tag:#3B1020; --on-tag:#FCEEEE; --hot:#C2255C; --display:'Fraunces', serif; --display-weight:600; --display-track:-0.015em; }
            .lp[data-theme="gadget"] { --paper:#EDF0F4; --surface:#FFFFFF; --ink:#0E1A2B; --soft:#556274; --line:#D8DEE7; --accent:#2450FF; --accent-deep:#1636C4; --tag:#FFE14D; --on-tag:#0E1A2B; --hot:#E5372B; --display:'Space Grotesk', sans-serif; --display-weight:700; --display-track:-0.03em; }
            .lp[data-theme="dapur"] { --paper:#FFF4D6; --surface:#FFFDF5; --ink:#2A1A0B; --soft:#6E5838; --line:#F0DFAE; --accent:#D93A1A; --accent-deep:#AE2B10; --tag:#2E7D32; --on-tag:#FFFFFF; --hot:#D93A1A; --display:'Lilita One', sans-serif; --display-weight:400; --display-track:0; }
            .lp[data-theme="belajar"] { --paper:#EEF3EC; --surface:#FFFFFF; --ink:#12261A; --soft:#4E6354; --line:#D5E0D2; --accent:#1E7A46; --accent-deep:#145A33; --tag:#F2C230; --on-tag:#12261A; --hot:#C2410C; --display:'Newsreader', serif; --display-weight:600; --display-track:-0.02em; }

            .lp { background:var(--paper); color:var(--ink); font-family:'Figtree', system-ui, sans-serif; font-size:16.5px; line-height:1.6; min-height:100vh; overflow-x:clip; -webkit-font-smoothing:antialiased; }
            .lp * { box-sizing:border-box; }
            .lp img, .lp video, .lp iframe { max-width:100%; }
            .lp-wrap { max-width:1080px; margin:0 auto; padding:0 18px; }
            .lp-read { max-width:680px; }
            .lp-display { font-family:var(--display); font-weight:var(--display-weight); letter-spacing:var(--display-track); line-height:1.1; text-wrap:balance; margin:0; }
            .lp-h2 { font-size:clamp(25px, 6.4vw, 36px); }
            .lp-soft { color:var(--soft); }
            .lp-section { padding:44px 0; border-top:1px solid var(--line); }

            /* Buttons: the CTA gets a periodic light sweep so the eye returns to it */
            .lp-btn { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; gap:10px; min-height:56px; padding:0 26px; border-radius:14px; background:var(--accent); color:var(--on-accent); font-weight:800; font-size:17px; line-height:1.2; text-align:center; text-decoration:none; box-shadow:0 3px 0 var(--accent-deep); transition:background .15s, transform .15s; -webkit-tap-highlight-color:transparent; }
            .lp-btn::after { content:''; position:absolute; inset:0; background:linear-gradient(105deg, transparent 35%, rgba(255,255,255,.45) 50%, transparent 65%); transform:translateX(-120%); animation:lp-shine 3.6s ease-in-out 1.2s infinite; pointer-events:none; }
            .lp-btn:hover { background:var(--accent-deep); }
            .lp-btn:active { transform:translateY(2px); box-shadow:0 1px 0 var(--accent-deep); }
            .lp-btn:focus-visible, .lp summary:focus-visible, .lp-thumb:focus-visible, .lp-play:focus-visible { outline:3px solid var(--ink); outline-offset:3px; }
            .lp-btn svg { width:20px; height:20px; flex:none; }
            .lp-btn-block { display:flex; width:100%; }
            @keyframes lp-shine { 0% { transform:translateX(-120%); } 30%, 100% { transform:translateX(120%); } }

            /* Urgency strip (only with a real promo deadline) */
            .lp-strip { position:sticky; top:0; z-index:45; background:var(--ink); color:var(--paper); font-size:14px; font-weight:600; text-align:center; padding:9px 14px; }
            .lp-strip b { font-variant-numeric:tabular-nums; }
            .lp-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#FF4D6D; margin-right:8px; vertical-align:1px; animation:lp-ping 1.4s ease-out infinite; }
            @keyframes lp-ping { 0% { box-shadow:0 0 0 0 rgba(255,77,109,.7); } 70%, 100% { box-shadow:0 0 0 9px rgba(255,77,109,0); } }

            /* Hero: photo first on phones so the product is the first thing seen */
            .lp-hero { display:grid; gap:22px; padding-top:16px; padding-bottom:40px; }
            .lp-hero h1 { font-size:clamp(30px, 8.6vw, 58px); }
            .lp-sub { margin:12px 0 0; font-size:17.5px; color:var(--soft); max-width:46ch; }
            .lp-stage { position:relative; order:-1; width:100%; max-width:480px; justify-self:center; }
            .lp-photo { aspect-ratio:1; width:100%; border-radius:22px; background:#fff; border:1px solid var(--line); object-fit:cover; display:block; }
            .lp-photo-empty { display:flex; align-items:center; justify-content:center; padding:32px; text-align:center; font-family:var(--display); font-weight:var(--display-weight); font-size:30px; background:var(--surface); }
            .lp-thumbs { display:flex; gap:8px; margin-top:10px; overflow-x:auto; scrollbar-width:none; }
            .lp-thumb { flex:none; width:58px; height:58px; padding:0; border-radius:12px; border:2px solid var(--line); background:#fff; cursor:pointer; overflow:hidden; }
            .lp-thumb[aria-pressed="true"] { border-color:var(--ink); }
            .lp-thumb img { width:100%; height:100%; object-fit:cover; display:block; }

            /* The discount sticker: the one loud element on the page */
            .lp-sticker { position:absolute; top:10px; right:10px; width:96px; height:96px; border-radius:50%; background:var(--tag); color:var(--on-tag); display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:10px; transform:rotate(-12deg); box-shadow:0 10px 22px -10px rgba(0,0,0,.45); animation:lp-slap .7s cubic-bezier(.2,1.6,.4,1) .25s both, lp-wobble 5s ease-in-out 2s infinite; }
            .lp-sticker::before { content:''; position:absolute; inset:5px; border-radius:50%; border:1.5px dashed currentColor; opacity:.45; }
            .lp-sticker small { font-size:11px; font-weight:700; line-height:1.15; }
            .lp-sticker strong { font-size:26px; font-weight:800; line-height:1; white-space:nowrap; font-variant-numeric:tabular-nums; }
            .lp-sticker strong.lp-sticker-price { font-size:15px; }
            @keyframes lp-slap { from { opacity:0; transform:rotate(-40deg) scale(1.7); } to { opacity:1; transform:rotate(-12deg) scale(1); } }
            @keyframes lp-wobble { 0%, 88%, 100% { transform:rotate(-12deg); } 92% { transform:rotate(-4deg) scale(1.06); } 96% { transform:rotate(-16deg); } }

            /* Price block with the struck-through normal price */
            .lp-price-block { margin-top:20px; display:flex; flex-wrap:wrap; align-items:center; gap:4px 12px; }
            .lp-was { color:var(--soft); text-decoration:line-through; text-decoration-thickness:2px; font-size:17px; }
            .lp-price { font-size:clamp(28px, 7.4vw, 36px); font-weight:800; line-height:1.1; font-variant-numeric:tabular-nums; }
            .lp-off { display:inline-flex; align-items:center; padding:3px 10px; border-radius:8px; background:var(--hot); color:#fff; font-weight:800; font-size:14px; }
            .lp-save { flex-basis:100%; font-size:14px; font-weight:700; color:var(--hot); }

            /* Countdown tiles; each tile re-pops when its value changes */
            .lp-count { margin-top:18px; }
            .lp-count p { margin:0 0 8px; font-size:14px; font-weight:700; }
            .lp-tiles { display:flex; gap:8px; }
            .lp-tile { min-width:58px; padding:8px 6px 6px; border-radius:12px; background:var(--ink); color:var(--paper); text-align:center; overflow:hidden; }
            .lp-tile b { display:block; font-size:24px; font-weight:800; line-height:1.1; font-variant-numeric:tabular-nums; animation-duration:.35s; animation-timing-function:cubic-bezier(.3,1.5,.5,1); }
            .lp-tile span { display:block; font-size:11px; opacity:.7; }
            @keyframes lp-pop-a { from { transform:translateY(-35%) scale(.85); opacity:.2; } to { transform:none; opacity:1; } }
            @keyframes lp-pop-b { from { transform:translateY(-35%) scale(.85); opacity:.2; } to { transform:none; opacity:1; } }
            .lp-ended { margin-top:16px; font-size:14px; font-weight:700; color:var(--soft); }

            .lp-cta-row { margin-top:20px; }
            .lp-assure { display:flex; align-items:center; gap:8px; margin:12px 0 0; font-size:14px; color:var(--soft); }
            .lp-assure svg { width:18px; height:18px; flex:none; color:var(--accent); }

            /* Problem */
            .lp-pains { list-style:none; padding:0; margin:20px 0 0; display:grid; gap:12px; }
            .lp-pains li { display:flex; gap:12px; align-items:flex-start; font-size:17px; }
            .lp-pains svg { flex:none; width:22px; height:22px; margin-top:2px; color:var(--hot); }

            /* Solution */
            .lp-solution p { font-size:19px; line-height:1.55; margin:16px 0 0; }

            /* Video: a poster until tapped, so the page stays fast */
            .lp-video { margin-top:22px; position:relative; border-radius:20px; overflow:hidden; background:#000; }
            .lp-video[data-vertical="true"] { aspect-ratio:9/16; max-width:340px; }
            .lp-video[data-vertical="false"] { aspect-ratio:16/9; }
            .lp-video iframe, .lp-video video, .lp-video img { position:absolute; inset:0; width:100%; height:100%; border:0; object-fit:cover; }
            .lp-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:linear-gradient(transparent 55%, rgba(0,0,0,.45)); border:0; cursor:pointer; padding:0; }
            .lp-play span { width:72px; height:72px; border-radius:50%; background:var(--accent); color:var(--on-accent); display:flex; align-items:center; justify-content:center; animation:lp-ring 2s ease-out infinite; }
            .lp-play svg { width:30px; height:30px; margin-left:4px; }
            @keyframes lp-ring { 0% { box-shadow:0 0 0 0 rgba(255,255,255,.55); } 80%, 100% { box-shadow:0 0 0 18px rgba(255,255,255,0); } }

            /* Benefits */
            .lp-benefits { display:grid; gap:22px 40px; margin-top:26px; }
            .lp-benefit { display:grid; grid-template-columns:34px 1fr; gap:14px; }
            .lp-benefit .lp-check { width:34px; height:34px; border-radius:50%; background:var(--accent); color:var(--on-accent); display:flex; align-items:center; justify-content:center; }
            .lp-benefit .lp-check svg { width:18px; height:18px; }
            .lp-benefit h3 { margin:3px 0 3px; font-size:18px; line-height:1.3; }
            .lp-benefit p { margin:0; color:var(--soft); }

            /* Steps: a real sequence, so numbered */
            .lp-steps { list-style:none; padding:0; margin:26px 0 0; display:grid; gap:18px; counter-reset:step; }
            .lp-steps li { counter-increment:step; display:grid; grid-template-columns:48px 1fr; gap:14px; align-items:start; }
            .lp-steps li::before { content:counter(step); font-family:var(--display); font-weight:var(--display-weight); font-size:40px; line-height:1; color:var(--accent); }
            .lp-steps h3 { margin:3px 0 2px; font-size:18px; }
            .lp-steps p { margin:0; color:var(--soft); }

            /* Comparison */
            .lp-compare { display:grid; gap:14px; margin-top:26px; }
            .lp-compare > div { border-radius:18px; padding:20px; }
            .lp-without { border:1px dashed var(--line); color:var(--soft); }
            .lp-with { background:var(--surface); border:2px solid var(--accent); }
            .lp-compare h3 { margin:0 0 12px; font-size:15px; font-weight:800; color:var(--ink); }
            .lp-compare ul { list-style:none; padding:0; margin:0; display:grid; gap:10px; }
            .lp-compare li { display:flex; gap:10px; }
            .lp-compare svg { flex:none; width:20px; height:20px; margin-top:3px; }
            .lp-with svg { color:var(--accent); }

            /* Suitable for */
            .lp-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:20px; padding:0; list-style:none; }
            .lp-chips li { padding:9px 15px; border-radius:999px; background:var(--surface); border:1px solid var(--line); font-weight:600; }

            /* FAQ */
            .lp-faq { margin-top:20px; border-top:1px solid var(--line); }
            .lp-faq details { border-bottom:1px solid var(--line); }
            .lp-faq summary { list-style:none; cursor:pointer; display:flex; justify-content:space-between; gap:16px; padding:18px 0; font-weight:700; font-size:17px; }
            .lp-faq summary::-webkit-details-marker { display:none; }
            .lp-faq summary svg { flex:none; width:22px; height:22px; transition:transform .2s; }
            .lp-faq details[open] summary svg { transform:rotate(45deg); }
            .lp-faq p { margin:0 0 18px; color:var(--soft); }

            /* Closing band; bottom padding leaves room for the sticky bar */
            .lp-closing { background:var(--ink); color:var(--paper); padding:60px 0 calc(60px + 96px); text-align:center; }
            .lp-closing h2 { font-size:clamp(28px, 7vw, 44px); }
            .lp-closing-text { margin:14px auto 0; max-width:52ch; opacity:.85; font-size:18px; }
            .lp-closing .lp-price-block { justify-content:center; }
            .lp-closing .lp-was { color:inherit; opacity:.6; }
            .lp-closing .lp-btn { margin-top:24px; box-shadow:none; }
            .lp-disclosure { max-width:60ch; margin:26px auto 0; font-size:12.5px; opacity:.6; }

            /* Sticky buy bar: always on phones, after the hero button on desktop */
            .lp-bar { position:fixed; left:0; right:0; bottom:0; z-index:40; background:var(--surface); border-top:1px solid var(--line); box-shadow:0 -8px 24px -16px rgba(0,0,0,.35); padding:10px 14px calc(10px + env(safe-area-inset-bottom)); }
            .lp-bar-inner { max-width:1080px; margin:0 auto; display:flex; align-items:center; gap:12px; }
            .lp-bar-price { flex:none; line-height:1.2; }
            .lp-bar-price s { display:block; font-size:12px; color:var(--soft); }
            .lp-bar-price b { font-size:18px; font-variant-numeric:tabular-nums; }
            .lp-bar-price small { display:block; font-size:12px; font-weight:700; color:var(--hot); font-variant-numeric:tabular-nums; }
            .lp-bar-title { display:none; flex:1; min-width:0; margin:0; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .lp-bar .lp-btn { flex:1; min-height:50px; font-size:16px; padding:0 16px; }

            .lp-preview { background:#FFE14D; color:#1A1A1A; text-align:center; font-size:14px; padding:9px 16px; }
            .lp-preview a { color:inherit; font-weight:700; }

            @media (min-width: 900px) {
                .lp { font-size:17px; }
                .lp-wrap { padding:0 24px; }
                .lp-hero { grid-template-columns:1.05fr .95fr; gap:64px; padding-top:64px; padding-bottom:84px; align-items:center; }
                .lp-stage { order:0; }
                .lp-photo { border-radius:28px; }
                .lp-sticker { top:-18px; right:-14px; width:132px; height:132px; }
                .lp-sticker strong { font-size:34px; }
                .lp-sticker strong.lp-sticker-price { font-size:20px; }
                .lp-sticker small { font-size:12px; }
                .lp-btn-block { display:inline-flex; width:auto; }
                .lp-section { padding:80px 0; }
                .lp-benefits, .lp-compare { grid-template-columns:1fr 1fr; }
                .lp-bar { transform:translateY(110%); transition:transform .25s ease; padding:12px 24px; }
                .lp-bar[data-shown="true"] { transform:none; }
                .lp-bar-title { display:block; }
                .lp-bar-price { order:2; text-align:right; }
                .lp-bar .lp-btn { order:3; flex:none; padding:0 26px; }
            }
            @media (prefers-reduced-motion: reduce) {
                .lp *, .lp *::before, .lp *::after { animation:none !important; transition:none !important; }
            }
        </style>
    @endpush

    <div class="lp" data-theme="{{ $theme }}"
         x-data="{
             left: {{ $secondsLeft ?? 'null' }},
             heroGone: false,
             pad(n) { return String(n).padStart(2, '0') },
             get d() { return Math.floor(this.left / 86400) },
             get h() { return Math.floor(this.left % 86400 / 3600) },
             get m() { return Math.floor(this.left % 3600 / 60) },
             get s() { return this.left % 60 },
             get clock() {
                 if (this.left === null || this.left <= 0) return null;
                 return (this.d ? this.d + ' hari ' : '') + this.pad(this.h) + ':' + this.pad(this.m) + ':' + this.pad(this.s);
             },
             pop(unit) { return 'animation-name:' + (Math.floor(this.left / unit) % 2 ? 'lp-pop-a' : 'lp-pop-b') },
         }"
         x-init="if (left) { const end = Date.now() + left * 1000; setInterval(() => left = Math.max(0, Math.round((end - Date.now()) / 1000)), 1000) }">

        @if ($preview)
            <div class="lp-preview">Preview: halaman ini belum online, hanya kamu yang bisa melihatnya. <a href="{{ route('dashboard.ai-agent', ['link' => $link->id]) }}">Kembali ke editor</a></div>
        @endif

        @if ($secondsLeft)
            <div class="lp-strip" role="status">
                <span x-show="clock"><span class="lp-dot" aria-hidden="true"></span>{{ $percent ? "Diskon {$percent}% berakhir dalam" : 'Promo berakhir dalam' }} <b x-text="clock"></b></span>
                <span x-show="!clock" style="display:none">Promo sudah berakhir. Cek harga terbaru di toko.</span>
            </div>
        @endif

        {{-- ===== Hero ===== --}}
        <header class="lp-wrap lp-hero">
            <div class="lp-stage" x-data="{ shown: 0 }">
                @if ($gallery)
                    @foreach ($gallery as $i => $src)
                        <img src="{{ $src }}" alt="{{ $link->title }}" class="lp-photo" @if ($i > 0) style="display:none" @endif x-show="shown === {{ $i }}" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @endforeach
                @else
                    <div class="lp-photo lp-photo-empty">{{ $link->title }}</div>
                @endif

                @if ($percent || $link->discount_tag || $link->formatted_price)
                    <div class="lp-sticker" aria-hidden="true">
                        @if ($percent)
                            <small>Hemat</small>
                            <strong>{{ $percent }}%</strong>
                        @elseif ($link->discount_tag)
                            <strong class="lp-sticker-price" style="white-space:normal">{{ $link->discount_tag }}</strong>
                        @else
                            <small>Cuma</small>
                            <strong class="lp-sticker-price">{{ $link->formatted_price }}</strong>
                        @endif
                    </div>
                @endif

                @if (count($gallery) > 1)
                    <div class="lp-thumbs" role="group" aria-label="Foto produk">
                        @foreach ($gallery as $i => $src)
                            <button type="button" class="lp-thumb" @click="shown = {{ $i }}" :aria-pressed="(shown === {{ $i }}).toString()" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" aria-label="Foto {{ $i + 1 }}">
                                <img src="{{ $src }}" alt="" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h1 class="lp-display">{{ $page['headline'] }}</h1>
                @if ($page['subheadline'])
                    <p class="lp-sub">{{ $page['subheadline'] }}</p>
                @endif

                @if ($link->formatted_price)
                    <div class="lp-price-block">
                        @if ($original)<span class="lp-was">{{ $original }}</span>@endif
                        <span class="lp-price">{{ $link->formatted_price }}</span>
                        @if ($percent)
                            <span class="lp-off">-{{ $percent }}%</span>
                            <span class="lp-save">Kamu hemat {{ $saving }}</span>
                        @elseif ($link->discount_tag)
                            <span class="lp-off">{{ $link->discount_tag }}</span>
                        @endif
                    </div>
                @endif

                @if ($secondsLeft)
                    <div class="lp-count" x-show="clock">
                        <p><span class="lp-dot" aria-hidden="true"></span>Harga promo berakhir dalam</p>
                        <div class="lp-tiles">
                            <div class="lp-tile" x-show="d > 0"><b x-text="d" :style="pop(86400)"></b><span>hari</span></div>
                            <div class="lp-tile"><b x-text="pad(h)" :style="pop(3600)"></b><span>jam</span></div>
                            <div class="lp-tile"><b x-text="pad(m)" :style="pop(60)"></b><span>menit</span></div>
                            <div class="lp-tile"><b x-text="pad(s)" :style="pop(1)"></b><span>detik</span></div>
                        </div>
                    </div>
                    <p class="lp-ended" x-show="!clock" style="display:none">Promo sudah berakhir. Harga di toko bisa sudah berubah.</p>
                @endif

                <div class="lp-cta-row" x-intersect:leave="heroGone = true" x-intersect:enter="heroGone = false">{!! $button('lp-btn-block') !!}</div>
                <p class="lp-assure">{!! $icon['shield'] !!}
                    <span>
                        @if ($viaChat)
                            Pesan langsung lewat WhatsApp, dibalas oleh penjual.
                        @elseif ($store)
                            Pembayaran dan pengiriman aman lewat {{ $store }}.
                        @else
                            Pembayaran dan pengiriman lewat toko resminya.
                        @endif
                    </span>
                </p>
            </div>
        </header>

        {{-- ===== Problem ===== --}}
        @if ($page['pain_points'])
            <section class="lp-section">
                <div class="lp-wrap"><div class="lp-read">
                    <h2 class="lp-display lp-h2">{{ $page['problem_title'] ?: 'Pernah ngerasain ini?' }}</h2>
                    <ul class="lp-pains">
                        @foreach ($page['pain_points'] as $point)
                            <li>{!! $icon['x'] !!} <span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </div></div>
            </section>
        @endif

        {{-- ===== Solution + video ===== --}}
        @if ($page['solution'] || $video)
            <section class="lp-section lp-solution">
                <div class="lp-wrap"><div class="lp-read">
                    <h2 class="lp-display lp-h2">{{ $page['solution_title'] ?: 'Kenalan sama ' . $link->title }}</h2>
                    @if ($page['solution'])
                        <p>{{ $page['solution'] }}</p>
                    @endif

                    @if ($video)
                        <div class="lp-video" data-vertical="{{ $video['vertical'] ? 'true' : 'false' }}" x-data="{ playing: false }">
                            @if ($video['type'] === 'file')
                                <video src="{{ $video['src'] }}" controls playsinline preload="metadata" @if ($gallery) poster="{{ $gallery[0] }}" @endif></video>
                            @else
                                <template x-if="playing">
                                    <iframe src="{{ $video['src'] }}" title="Video {{ $link->title }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                                </template>
                                @if ($video['type'] === 'youtube')
                                    {{-- Shorts have a vertical thumbnail; fall back to the standard one --}}
                                    <img x-show="!playing" src="https://i.ytimg.com/vi/{{ $video['id'] }}/{{ $video['vertical'] ? 'oardefault' : 'hqdefault' }}.jpg" onerror="this.onerror=null;this.src='https://i.ytimg.com/vi/{{ $video['id'] }}/hqdefault.jpg'" alt="" loading="lazy">
                                @elseif ($video['type'] === 'drive')
                                    {{-- Drive serves a thumbnail only for publicly shared files; otherwise fall back to the product photo --}}
                                    <img x-show="!playing" src="https://drive.google.com/thumbnail?id={{ $video['id'] }}&amp;sz=w1000" @if ($gallery) onerror="this.onerror=null;this.src=@js($gallery[0])" @else onerror="this.remove()" @endif alt="" loading="lazy" referrerpolicy="no-referrer">
                                @elseif ($gallery)
                                    <img x-show="!playing" src="{{ $gallery[0] }}" alt="" loading="lazy">
                                @endif
                                <button type="button" class="lp-play" x-show="!playing" @click="playing = true" aria-label="Putar video"><span>{!! $icon['play'] !!}</span></button>
                            @endif
                        </div>
                    @endif
                </div></div>
            </section>
        @endif

        {{-- ===== Benefits ===== --}}
        @if ($page['benefits'])
            <section class="lp-section">
                <div class="lp-wrap">
                    <h2 class="lp-display lp-h2 lp-read">{{ $page['benefits_title'] ?: 'Yang kamu dapat' }}</h2>
                    <div class="lp-benefits">
                        @foreach ($page['benefits'] as $benefit)
                            <div class="lp-benefit">
                                <span class="lp-check">{!! $icon['check'] !!}</span>
                                <div>
                                    <h3>{{ $benefit['title'] }}</h3>
                                    @if ($benefit['desc'])<p>{{ $benefit['desc'] }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="lp-read" style="margin-top:32px">{!! $button('lp-btn-block') !!}</div>
                </div>
            </section>
        @endif

        {{-- ===== How to use ===== --}}
        @if ($page['steps'])
            <section class="lp-section">
                <div class="lp-wrap"><div class="lp-read">
                    <h2 class="lp-display lp-h2">Cara pakainya</h2>
                    <ol class="lp-steps">
                        @foreach ($page['steps'] as $step)
                            <li><div><h3>{{ $step['title'] }}</h3>@if ($step['desc'])<p>{{ $step['desc'] }}</p>@endif</div></li>
                        @endforeach
                    </ol>
                </div></div>
            </section>
        @endif

        {{-- ===== Without vs with ===== --}}
        @if ($comparison['without'] && $comparison['with'])
            <section class="lp-section">
                <div class="lp-wrap">
                    <h2 class="lp-display lp-h2 lp-read">Bedanya terasa</h2>
                    <div class="lp-compare">
                        <div class="lp-without">
                            <h3>Tanpa {{ \Illuminate\Support\Str::limit($link->title, 40) }}</h3>
                            <ul>@foreach ($comparison['without'] as $item)<li>{!! $icon['x'] !!} <span>{{ $item }}</span></li>@endforeach</ul>
                        </div>
                        <div class="lp-with">
                            <h3>Dengan {{ \Illuminate\Support\Str::limit($link->title, 40) }}</h3>
                            <ul>@foreach ($comparison['with'] as $item)<li>{!! $icon['check'] !!} <span>{{ $item }}</span></li>@endforeach</ul>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ===== Suitable for ===== --}}
        @if ($page['suitable_for'])
            <section class="lp-section">
                <div class="lp-wrap"><div class="lp-read">
                    <h2 class="lp-display lp-h2">Cocok buat kamu yang…</h2>
                    <ul class="lp-chips">
                        @foreach ($page['suitable_for'] as $who)<li>{{ $who }}</li>@endforeach
                    </ul>
                </div></div>
            </section>
        @endif

        {{-- ===== FAQ ===== --}}
        @if ($page['faq'])
            <section class="lp-section">
                <div class="lp-wrap"><div class="lp-read">
                    <h2 class="lp-display lp-h2">Masih ragu?</h2>
                    <div class="lp-faq">
                        @foreach ($page['faq'] as $item)
                            <details>
                                <summary>{{ $item['q'] }} {!! $icon['plus'] !!}</summary>
                                <p>{{ $item['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div></div>
            </section>
        @endif

        {{-- ===== Closing ===== --}}
        <section class="lp-closing">
            <div class="lp-wrap">
                <h2 class="lp-display">{{ $page['closing_title'] ?: 'Siap coba sendiri?' }}</h2>
                @if ($page['closing'])
                    <p class="lp-closing-text">{{ $page['closing'] }}</p>
                @endif
                @if ($link->formatted_price)
                    <div class="lp-price-block">
                        @if ($original)<span class="lp-was">{{ $original }}</span>@endif
                        <span class="lp-price">{{ $link->formatted_price }}</span>
                        @if ($percent)<span class="lp-off">-{{ $percent }}%</span>@endif
                    </div>
                @endif
                {!! $button() !!}
                <p class="lp-disclosure">Halaman ini berisi link affiliate. Pembelian lewat link ini bisa memberi komisi untuk pembuat halaman, tanpa biaya tambahan untukmu.</p>
            </div>
        </section>

        {{-- ===== Sticky buy bar ===== --}}
        <div class="lp-bar" :data-shown="heroGone.toString()" data-shown="false">
            <div class="lp-bar-inner">
                <p class="lp-bar-title">{{ $link->title }}</p>
                @if ($link->formatted_price)
                    <div class="lp-bar-price">
                        @if ($original)<s>{{ $original }}</s>@endif
                        <b>{{ $link->formatted_price }}</b>
                        @if ($secondsLeft)<small x-show="clock" x-text="clock"></small>@endif
                    </div>
                @endif
                {!! $button() !!}
            </div>
        </div>
    </div>
</x-layouts.app>
