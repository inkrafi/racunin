<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2B0B3F">
    <meta name="description" content="{{ $ogDescription ?? 'Short link, katalog bio, dan statistik klik untuk affiliate Shopee, Tokopedia, TikTok Shop, dan lainnya.' }}">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/pwa-192x192.png">

    <title>{{ $title ?? 'Racunin' }}</title>

    <meta property="og:title" content="{{ $ogTitle ?? $title ?? 'Racunin' }}">
    <meta property="og:description" content="{{ $ogDescription ?? 'Short link, katalog bio, dan statistik klik untuk affiliate Shopee, Tokopedia, TikTok Shop, dan lainnya.' }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('pwa-512x512.png') }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400..800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-full flex flex-col">

    {{-- Thin progress bar while Livewire is talking to the server --}}
    <div wire:loading.delay class="fixed top-0 inset-x-0 z-[999] h-0.5 bg-racun"></div>

    {{-- Toasts: session flashes on page load + "toast" events dispatched by Livewire --}}
    <div
        x-data="{
            toasts: [],
            add(message, type = 'success') {
                if (!message) return;
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message, type });
                setTimeout(() => this.remove(id), 4500);
            },
            remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); }
        }"
        x-init="
            @if (session()->has('message')) add(@js(session('message')), 'success'); @endif
            @if (session()->has('error')) add(@js(session('error')), 'error'); @endif
            window.addEventListener('toast', (e) => {
                const data = e.detail[0] || e.detail;
                add(data.message || data, data.type || 'success');
            });
        "
        class="fixed z-[999] inset-x-0 bottom-20 sm:bottom-6 flex flex-col items-center gap-2 px-4 pointer-events-none"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto flex items-start gap-3 max-w-md w-full sm:w-auto pl-4 pr-2 py-2.5 rounded-xl text-sm font-medium shadow-[0_12px_32px_-8px_rgba(43,11,63,0.4)]"
                :class="toast.type === 'error' ? 'bg-alert text-white' : 'bg-ink text-white'"
                role="status"
            >
                <p class="flex-1 py-0.5 leading-snug" x-text="toast.message"></p>
                <button type="button" @click="remove(toast.id)" class="shrink-0 p-1 rounded-md text-white/70 hover:text-white cursor-pointer" aria-label="Tutup notifikasi">
                    <x-icon name="x" />
                </button>
            </div>
        </template>
    </div>

    @if (request()->routeIs('dashboard*'))
        @php
            $navItems = [
                ['route' => 'dashboard', 'label' => 'Link', 'icon' => 'link'],
                ['route' => 'dashboard.ai-agent', 'label' => 'AI Agent', 'icon' => 'wand'],
                ['route' => 'dashboard.analytics', 'label' => 'Statistik', 'icon' => 'chart'],
                ['route' => 'dashboard.commissions', 'label' => 'Komisi', 'icon' => 'coin'],
                ['route' => 'dashboard.catalog', 'label' => 'Katalog', 'icon' => 'store'],
                ['route' => 'dashboard.discovery', 'label' => 'Ide produk', 'icon' => 'bulb'],
            ];
        @endphp

        {{-- ===== App shell: top bar (desktop) + bottom tab bar (phone) ===== --}}
        <header class="sticky top-0 z-40 bg-plum text-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center gap-8">
                <x-logo :href="route('dashboard')" dark />

                <nav class="hidden sm:flex items-center gap-1 h-full" aria-label="Navigasi utama">
                    @foreach ($navItems as $item)
                        @php $active = request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route']) }}"
                           @class([
                               'relative h-full inline-flex items-center px-3 text-sm font-semibold transition-colors',
                               'text-white after:absolute after:inset-x-3 after:bottom-0 after:h-[3px] after:rounded-t after:bg-lime' => $active,
                               'text-plum-ink hover:text-white' => ! $active,
                           ])
                           @if ($active) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                @auth
                    <div class="ml-auto flex items-center gap-3" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="flex items-center gap-2.5 rounded-lg py-1 pl-1 pr-2 hover:bg-white/10 cursor-pointer" :aria-expanded="open">
                            <span class="size-8 rounded-full bg-lime text-plum text-[13px] font-bold inline-flex items-center justify-center">
                                {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden md:block text-left leading-tight">
                                <span class="block text-sm font-semibold">{{ Str::limit(Auth::user()->name, 24) }}</span>
                                @if (Auth::user()->isAdmin())
                                    <span class="block text-xs text-plum-ink">Admin</span>
                                @endif
                            </span>
                        </button>

                        <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                             class="absolute right-4 sm:right-6 top-14 mt-1 w-56 panel text-ink shadow-[0_12px_32px_-8px_rgba(43,11,63,0.25)] p-1.5">
                            <p class="px-2.5 py-2 text-xs text-ink-soft truncate">{{ Auth::user()->email }}</p>
                            <a href="/" class="flex items-center gap-2.5 px-2.5 h-9 rounded-md text-sm hover:bg-paper">
                                <x-icon name="external" class="text-ink-soft" /> Halaman depan
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 px-2.5 h-9 rounded-md text-sm text-alert hover:bg-alert-wash cursor-pointer">
                                    <x-icon name="logout" /> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        <main class="flex-1 pb-20 sm:pb-0">
            {{ $slot }}
        </main>

        <nav class="sm:hidden fixed bottom-0 inset-x-0 z-40 bg-surface border-t border-line grid grid-cols-6 pb-[env(safe-area-inset-bottom)]" aria-label="Navigasi utama">
            @foreach ($navItems as $item)
                @php $active = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex flex-col items-center justify-center gap-1 h-16 text-[11px] font-semibold',
                       'text-racun' => $active,
                       'text-ink-soft' => ! $active,
                   ])
                   @if ($active) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" class="size-5" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

    @elseif (request()->routeIs('catalog.public*') || request()->routeIs('lp.*') || request()->is('/'))
        {{-- Public catalog and landing page draw their own chrome --}}
        <main class="flex-1">
            {{ $slot }}
        </main>

    @else
        {{-- ===== Auth / guest pages ===== --}}
        <header class="border-b border-line bg-surface">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                <x-logo />
                <nav class="flex items-center gap-1 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary">Buka dashboard</a>
                    @else
                        @unless (request()->routeIs('login'))
                            <a href="{{ route('login') }}" class="btn btn-sm btn-ghost">Masuk</a>
                        @endunless
                        @unless (request()->routeIs('register'))
                            <a href="{{ route('register') }}" class="btn btn-sm btn-primary">Daftar</a>
                        @endunless
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line py-6 text-[13px] text-ink-soft">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">© {{ date('Y') }} Racunin</div>
        </footer>
    @endif

    @livewireScripts
    @stack('scripts')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</body>
</html>
