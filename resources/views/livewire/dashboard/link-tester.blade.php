<div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-6"
     x-data="{
        copied: null,
        copy(key, text) {
            navigator.clipboard.writeText(text);
            this.copied = key;
            setTimeout(() => this.copied = null, 1800);
        }
     }">
    @php
        $appNames = [
            'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'threads' => 'Threads',
            'line' => 'LINE', 'webview' => 'Aplikasi lain (WebView)', 'chrome' => 'Chrome', 'safari' => 'Safari',
            'firefox' => 'Firefox', 'edge' => 'Edge', 'opera' => 'Opera', 'other' => 'Browser lain', 'bot' => 'Bot',
        ];
        $osNames = ['android' => 'Android', 'ios' => 'iOS', 'windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux'];
        $store = $link?->platform_label ?? 'toko';
        $plainUrl = $link ? $link->short_url . '?app=0' : null;
        $appUrl = $link ? $link->short_url . '?app=1' : null;
    @endphp

    <div>
        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-racun hover:underline">Kembali ke daftar link</a>
        <h1 class="mt-2 text-2xl sm:text-[28px] font-extrabold tracking-tight">Tes link di HP</h1>
        <p class="mt-1 text-ink-soft max-w-[60ch]">
            Instagram, TikTok, dan Facebook membuka link di browser bawaannya. Di sana pembeli sering belum login,
            jadi lebih jarang checkout. Cek di HP kamu apakah link membuka aplikasi {{ $store }} atau hanya versi webnya.
        </p>
    </div>

    @if ($links->isEmpty())
        <section class="panel p-6 text-center">
            <p class="font-semibold">Belum ada link aktif untuk dites</p>
            <a href="{{ route('dashboard') }}" class="btn btn-primary mt-4">Buat link</a>
        </section>
    @else
        <section class="panel p-5 sm:p-6 space-y-5">
            <div>
                <label for="test-link" class="field-label">Link yang dites</label>
                <select id="test-link" wire:change="selectLink($event.target.value)" class="field">
                    @foreach ($links as $l)
                        <option value="{{ $l->id }}" @selected($l->id === $linkId)>{{ Str::limit($l->title, 48) }} (/{{ $l->slug }})</option>
                    @endforeach
                </select>
            </div>

            @if ($link)
                <ol class="space-y-5">
                    <li>
                        <p class="font-bold">1. Kirim dua versi link ini ke dirimu sendiri</p>
                        <p class="mt-0.5 text-sm text-ink-soft">Misalnya lewat DM Instagram, chat WhatsApp ke nomor sendiri, atau taruh sementara di bio TikTok.</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-line p-4">
                                <p class="font-semibold">A. Redirect biasa</p>
                                <p class="mt-0.5 text-[13px] text-ink-soft">Langsung ke halaman {{ $store }}.</p>
                                <p class="mt-3 text-sm font-semibold break-all">{{ preg_replace('#^https?://#', '', $plainUrl) }}</p>
                                <button type="button" @click="copy('plain', @js($plainUrl))" class="btn btn-sm btn-secondary mt-3 w-full">
                                    <span x-show="copied !== 'plain'"><x-icon name="copy" /> Salin link A</span>
                                    <span x-show="copied === 'plain'" x-cloak><x-icon name="check" /> Tersalin</span>
                                </button>
                            </div>

                            <div class="rounded-xl border p-4 {{ $supportsApp ? 'border-racun bg-racun-wash/40' : 'border-line' }}">
                                <p class="font-semibold">B. Coba buka aplikasi</p>
                                @if ($supportsApp)
                                    <p class="mt-0.5 text-[13px] text-ink-soft">Di Android, coba buka aplikasi {{ $store }} dulu.</p>
                                    <p class="mt-3 text-sm font-semibold break-all">{{ preg_replace('#^https?://#', '', $appUrl) }}</p>
                                    <button type="button" @click="copy('app', @js($appUrl))" class="btn btn-sm btn-secondary mt-3 w-full">
                                        <span x-show="copied !== 'app'"><x-icon name="copy" /> Salin link B</span>
                                        <span x-show="copied === 'app'" x-cloak><x-icon name="check" /> Tersalin</span>
                                    </button>
                                @else
                                    <p class="mt-0.5 text-[13px] text-ink-soft">Belum tersedia untuk link ini. Cara ini hanya bisa untuk toko yang punya aplikasi Android: Shopee, Tokopedia, Lazada, Blibli, Bukalapak, dan Zalora.</p>
                                @endif
                            </div>
                        </div>
                        <p class="mt-2 text-[13px] text-ink-soft">Di iPhone, link B sama dengan link A. iOS tidak mengizinkan aplikasi lain dibuka dari browser bawaan Instagram atau TikTok.</p>
                    </li>

                    <li>
                        <p class="font-bold">2. Ketuk link dari dalam tiap aplikasi, lalu catat hasilnya</p>
                        <p class="mt-0.5 text-sm text-ink-soft">Catatan hanya tersimpan di browser ini.</p>

                        <div class="mt-3 rounded-xl border border-line overflow-hidden"
                             x-data="{
                                results: {},
                                init() {
                                    try { this.results = JSON.parse(localStorage.getItem('racunin:app-test') || '{}'); } catch (e) { this.results = {}; }
                                    this.$watch('results', (value) => { try { localStorage.setItem('racunin:app-test', JSON.stringify(value)); } catch (e) {} });
                                }
                             }">
                            <div class="grid grid-cols-[minmax(0,1fr)_7.5rem_7.5rem] gap-2 px-3 py-2 bg-paper text-[13px] font-semibold text-ink-soft">
                                <span>Dibuka dari</span><span>Link A</span><span>Link B</span>
                            </div>
                            @foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook / Messenger', 'threads' => 'Threads', 'telegram' => 'Telegram', 'x' => 'X'] as $key => $name)
                                <div class="grid grid-cols-[minmax(0,1fr)_7.5rem_7.5rem] gap-2 items-center px-3 py-2 border-t border-line">
                                    <span class="text-sm font-semibold truncate">{{ $name }}</span>
                                    @foreach (['a', 'b'] as $variant)
                                        <label class="sr-only" for="r-{{ $key }}-{{ $variant }}">Hasil {{ $name }} link {{ strtoupper($variant) }}</label>
                                        <select id="r-{{ $key }}-{{ $variant }}" x-model="results['{{ $key }}-{{ $variant }}']" class="field h-9 text-[13px] px-2">
                                            <option value="">Belum</option>
                                            <option value="app">Aplikasi toko</option>
                                            <option value="web">Web toko</option>
                                            <option value="error">Error</option>
                                        </select>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </li>

                    <li>
                        <p class="font-bold">3. Cek klik yang tercatat</p>
                        <p class="mt-0.5 text-sm text-ink-soft">Tiap ketukan muncul di sini dalam beberapa detik, beserta aplikasi yang dikenali Racunin.</p>

                        <div wire:poll.3s.visible class="mt-3 rounded-xl border border-line divide-y divide-line">
                            @forelse ($clicks as $click)
                                <div class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                                    <span class="font-semibold">{{ $appNames[$click->browser] ?? ucfirst((string) $click->browser) }}</span>
                                    <span class="text-ink-soft text-right">
                                        {{ $osNames[$click->platform] ?? 'Perangkat lain' }}, {{ $click->clicked_at->diffForHumans() }}
                                    </span>
                                </div>
                            @empty
                                <p class="px-3 py-6 text-center text-sm text-ink-soft">Belum ada klik. Ketuk salah satu link di atas dari HP kamu.</p>
                            @endforelse
                        </div>
                    </li>
                </ol>
            @endif
        </section>

        @if ($isAdmin)
            <section class="panel p-5 sm:p-6">
                <h2 class="font-bold">Untuk admin</h2>
                <p class="mt-1 text-sm text-ink-soft leading-relaxed">
                    Mode buka aplikasi saat ini <span class="font-semibold text-ink">{{ $openInAppDefault ? 'menyala' : 'mati' }}</span> untuk semua link.
                    Kalau link B lebih sering membuka aplikasi toko di Instagram, TikTok, dan Facebook tanpa error,
                    nyalakan dengan <code class="px-1 rounded bg-paper">AFFILIATE_OPEN_IN_APP=true</code> di <code class="px-1 rounded bg-paper">.env</code>.
                    Tambahan <code class="px-1 rounded bg-paper">?app=0</code> atau <code class="px-1 rounded bg-paper">?app=1</code> tetap bisa dipakai untuk tes.
                </p>
            </section>
        @endif
    @endif
</div>
