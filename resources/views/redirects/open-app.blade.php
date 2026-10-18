@php
    $store = $platform['label'] ?? 'toko';
    $title = $link['og_title'] ?: $link['title'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#F5EFFA">
    <title>Membuka {{ $store }}…</title>
    {{-- Standalone page: no app bundle, so it renders instantly inside a social app's webview --}}
    <style>
        :root { --paper: #F5EFFA; --ink: #22092F; --ink-soft: #6D5A78; --line: #E7DCEF; --racun: #E0186C; --racun-deep: #B30F53; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               background: var(--paper); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 380px; }
        img { width: 72px; height: 72px; object-fit: cover; border-radius: 12px; border: 1px solid var(--line); background: #fff; }
        h1 { margin: 16px 0 4px; font-size: 22px; line-height: 1.25; letter-spacing: -0.02em; }
        p { margin: 0; color: var(--ink-soft); }
        .product { margin-top: 4px; font-weight: 600; color: var(--ink); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .actions { margin-top: 28px; display: grid; gap: 8px; }
        a.btn { display: flex; align-items: center; justify-content: center; height: 48px; border-radius: 10px; font-weight: 700; text-decoration: none; }
        .primary { background: var(--racun); color: #fff; }
        .primary:active { background: var(--racun-deep); }
        .secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .hint { margin-top: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <main>
        @if ($link['og_image_url'])
            <img src="{{ $link['og_image_url'] }}" alt="">
        @endif
        <h1>Membuka aplikasi {{ $store }}…</h1>
        <p class="product">{{ $title }}</p>

        <div class="actions">
            <a class="btn primary" href="{{ $intentUrl }}">Buka di aplikasi {{ $store }}</a>
            <a class="btn secondary" href="{{ $link['original_url'] }}" rel="nofollow noopener">Lanjut di browser</a>
        </div>
        <p class="hint">Belum punya aplikasinya? Pilih “Lanjut di browser”.</p>
    </main>

    <script>
        // Try the app right away; if the page is still visible after a moment
        // (app missing or the webview blocked the intent), continue on the web.
        window.location.href = @json($intentUrl);
        setTimeout(function () {
            if (!document.hidden) window.location.replace(@json($link['original_url']));
        }, 2500);
    </script>
</body>
</html>
