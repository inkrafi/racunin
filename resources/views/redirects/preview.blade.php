@php
    $ogTitle = $link['og_title'] ?: $link['title'];
    $ogDescription = $link['og_description'] ?: ($link['description'] ?: \App\Models\Link::defaultOgDescription($link['original_url']));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $ogTitle }}</title>
    <meta name="description" content="{{ $ogDescription }}">

    <meta property="og:type" content="product">
    <meta property="og:site_name" content="{{ config('app.name', 'Racunin') }}">
    <meta property="og:url" content="{{ $shortUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    @if ($link['og_image_url'])
        <meta property="og:image" content="{{ $link['og_image_url'] }}">
        <meta name="twitter:image" content="{{ $link['og_image_url'] }}">
    @endif
    <meta name="twitter:card" content="{{ $link['og_image_url'] ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
</head>
<body>
    {{-- Only crawlers are served this page; if a human gets it (unusual UA), send them on --}}
    <p><a href="{{ $link['original_url'] }}" rel="nofollow noopener">{{ $ogTitle }}</a></p>
    <script>window.location.replace(@json($link['original_url']));</script>
</body>
</html>
