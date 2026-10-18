<div class="px-4 py-12 sm:py-20">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-extrabold tracking-tight">Masuk ke Racunin</h1>
        <p class="mt-1 text-ink-soft">Kelola link, katalog, dan statistik klik kamu.</p>

        <a href="{{ route('auth.google') }}" class="btn btn-secondary w-full h-11 mt-8">
            <x-icon name="google" /> Masuk dengan Google
        </a>

        <div class="my-6 flex items-center gap-3 text-[13px] text-ink-soft">
            <span class="h-px flex-1 bg-line"></span> atau pakai email <span class="h-px flex-1 bg-line"></span>
        </div>

        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" wire:model="email" autocomplete="email" required autofocus class="field h-11" placeholder="nama@email.com">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">Kata sandi</label>
                <input type="password" id="password" wire:model="password" autocomplete="current-password" required class="field h-11">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2.5 text-sm cursor-pointer">
                <input type="checkbox" wire:model="remember" class="size-4 accent-racun"> Ingat saya di perangkat ini
            </label>

            <button type="submit" wire:loading.attr="disabled" wire:target="login" class="btn btn-primary w-full h-11">
                <span wire:loading.remove wire:target="login">Masuk</span>
                <span wire:loading wire:target="login">Memeriksa…</span>
            </button>
        </form>

        <p class="mt-8 text-sm text-ink-soft">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-racun hover:underline">Daftar gratis</a></p>
    </div>
</div>
