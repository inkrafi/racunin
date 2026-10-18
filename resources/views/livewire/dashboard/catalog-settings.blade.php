<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
    @php
        $themes = \App\Models\Catalog::themes();
        $activeTheme = $themes[$theme] ?? $themes['orange'];
        $socialFields = [
            'instagram' => ['label' => 'Instagram', 'prefix' => 'instagram.com/', 'placeholder' => 'namakamu'],
            'tiktok' => ['label' => 'TikTok', 'prefix' => 'tiktok.com/@', 'placeholder' => 'namakamu'],
            'whatsapp' => ['label' => 'WhatsApp', 'prefix' => 'wa.me/', 'placeholder' => '6281234567890'],
            'telegram' => ['label' => 'Telegram', 'prefix' => 't.me/', 'placeholder' => 'namakamu'],
            'youtube' => ['label' => 'YouTube', 'prefix' => null, 'placeholder' => 'https://youtube.com/@namakamu'],
        ];
    @endphp

    <div class="flex flex-col gap-1">
        <h1 class="text-2xl sm:text-[28px] font-extrabold tracking-tight">Katalog</h1>
        <p class="text-ink-soft max-w-2xl">Halaman berisi produk dari link kamu. Pasang alamatnya di bio Instagram atau TikTok supaya pengikut bisa belanja dari satu tempat.</p>
    </div>

    {{-- Public address --}}
    <div class="mt-6 panel p-4 flex flex-col gap-3 sm:flex-row sm:items-center" x-data="{ copied: false }">
        <div class="min-w-0 flex-1">
            <p class="text-[13px] text-ink-soft">Alamat katalog kamu</p>
            <p class="font-semibold truncate text-[15px]">{{ $publicUrl }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn btn-secondary flex-1 sm:flex-none" @click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 1800)">
                <x-icon name="copy" /> <span x-text="copied ? 'Tersalin' : 'Salin alamat'"></span>
            </button>
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-secondary flex-1 sm:flex-none"><x-icon name="external" /> Buka</a>
        </div>
    </div>

    <form wire:submit="saveCatalog" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="space-y-6">
            {{-- Profile --}}
            <section class="panel p-5 sm:p-6 space-y-4">
                <h2 class="font-bold">Profil</h2>

                <div>
                    <label for="username" class="field-label">Username</label>
                    <div class="flex items-stretch rounded-lg border border-line-strong bg-surface focus-within:border-racun focus-within:ring-3 focus-within:ring-racun/15 overflow-hidden">
                        <span class="inline-flex items-center pl-3 text-sm text-ink-faint select-none whitespace-nowrap">{{ preg_replace('#^https?://#', '', url('/')) }}/@</span>
                        <input type="text" id="username" wire:model.live.debounce.400ms="username" class="flex-1 min-w-0 h-10 pr-3 bg-transparent text-sm font-semibold outline-none">
                    </div>
                    @error('username') <p class="field-error">{{ $message }}</p> @else <p class="field-hint">Huruf, angka, tanda hubung, atau garis bawah. Minimal 3 karakter.</p> @enderror
                </div>

                <div>
                    <label for="display_name" class="field-label">Nama yang ditampilkan</label>
                    <input type="text" id="display_name" wire:model.live.debounce.400ms="display_name" class="field" placeholder="Racun Skincare Rina">
                    @error('display_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="bio" class="field-label">Bio <span class="font-normal text-ink-soft">(opsional)</span></label>
                    <textarea id="bio" wire:model.live.debounce.400ms="bio" rows="3" class="field" placeholder="Skincare yang sudah aku coba sendiri, dengan harga terbaik."></textarea>
                    @error('bio') <p class="field-error">{{ $message }}</p> @else <p class="field-hint">Maksimal 500 karakter.</p> @enderror
                </div>

                <div>
                    <label for="avatar_url" class="field-label">Link foto profil <span class="font-normal text-ink-soft">(opsional)</span></label>
                    <input type="url" id="avatar_url" wire:model.live.debounce.500ms="avatar_url" class="field" placeholder="https://…">
                    @error('avatar_url') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </section>

            {{-- Theme --}}
            <section class="panel p-5 sm:p-6">
                <h2 class="font-bold">Warna</h2>
                <p class="text-sm text-ink-soft">Dipakai untuk tombol beli dan kategori di katalog.</p>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-2" role="radiogroup" aria-label="Warna katalog">
                    @foreach ($themes as $key => $option)
                        <button type="button" role="radio" aria-checked="{{ $theme === $key ? 'true' : 'false' }}" wire:click="$set('theme', '{{ $key }}')"
                                @class([
                                    'flex items-center gap-2.5 h-11 px-3 rounded-lg border text-sm font-semibold cursor-pointer transition-colors',
                                    'border-ink ring-1 ring-ink' => $theme === $key,
                                    'border-line-strong hover:bg-paper' => $theme !== $key,
                                ])>
                            <span class="size-5 rounded-full shrink-0" style="background: {{ $option['accent'] }}"></span>
                            {{ $option['name'] }}
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Socials --}}
            <section class="panel p-5 sm:p-6 space-y-4">
                <div>
                    <h2 class="font-bold">Media sosial</h2>
                    <p class="text-sm text-ink-soft">Isi yang kamu pakai saja. Yang kosong tidak ditampilkan.</p>
                </div>
                @foreach ($socialFields as $key => $field)
                    <div>
                        <label for="social-{{ $key }}" class="field-label">{{ $field['label'] }}</label>
                        @if ($field['prefix'])
                            <div class="flex items-stretch rounded-lg border border-line-strong bg-surface focus-within:border-racun focus-within:ring-3 focus-within:ring-racun/15 overflow-hidden">
                                <span class="inline-flex items-center pl-3 text-sm text-ink-faint select-none whitespace-nowrap">{{ $field['prefix'] }}</span>
                                <input type="text" id="social-{{ $key }}" wire:model="social_links.{{ $key }}" placeholder="{{ $field['placeholder'] }}" class="flex-1 min-w-0 h-10 pr-3 bg-transparent text-sm outline-none">
                            </div>
                        @else
                            <input type="text" id="social-{{ $key }}" wire:model="social_links.{{ $key }}" placeholder="{{ $field['placeholder'] }}" class="field">
                        @endif
                        @error('social_links.' . $key) <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </section>
        </div>

        {{-- Preview + publish (sticky on desktop) --}}
        <aside class="space-y-4 lg:sticky lg:top-20">
            <div>
                <p class="text-[13px] text-ink-soft mb-2">Pratinjau</p>
                <div class="panel overflow-hidden" aria-hidden="true">
                    <div class="px-5 pt-6 pb-5 text-center" style="background: {{ $activeTheme['wash'] }}">
                        @if ($avatar_url)
                            <img src="{{ $avatar_url }}" alt="" class="size-16 rounded-full object-cover mx-auto border-2 border-white">
                        @else
                            <span class="size-16 rounded-full mx-auto inline-flex items-center justify-center text-xl font-bold text-white" style="background: {{ $activeTheme['accent'] }}">
                                {{ mb_strtoupper(mb_substr($display_name ?: 'K', 0, 1)) }}
                            </span>
                        @endif
                        <p class="mt-3 font-bold leading-snug">{{ $display_name ?: 'Nama katalog' }}</p>
                        <p class="text-[13px] text-ink-soft">{{ '@' . ($username ?: 'username') }}</p>
                        @if ($bio)
                            <p class="mt-2 text-[13px] leading-relaxed line-clamp-3">{{ $bio }}</p>
                        @endif
                    </div>
                    <div class="p-4 space-y-2">
                        @foreach (range(1, 2) as $i)
                            <div class="flex items-center gap-3">
                                <span class="size-10 rounded-md bg-paper shrink-0"></span>
                                <span class="flex-1 space-y-1.5"><span class="block h-2.5 rounded bg-paper w-4/5"></span><span class="block h-2.5 rounded bg-paper w-2/5"></span></span>
                                <span class="h-7 w-14 rounded-md shrink-0" style="background: {{ $activeTheme['accent'] }}"></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="panel p-4 space-y-4">
                <label class="flex items-start justify-between gap-4 cursor-pointer">
                    <span>
                        <span class="block text-sm font-semibold">Katalog bisa dilihat publik</span>
                        <span class="block text-[13px] text-ink-soft">Matikan untuk menyembunyikan sementara.</span>
                    </span>
                    <input type="checkbox" wire:model="is_active" class="mt-0.5 size-5 accent-racun cursor-pointer shrink-0">
                </label>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveCatalog" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="saveCatalog">Simpan katalog</span>
                    <span wire:loading wire:target="saveCatalog">Menyimpan…</span>
                </button>
            </div>
        </aside>
    </form>
</div>
