<div class="px-4 py-12 sm:py-20">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-extrabold tracking-tight">Buat akun Racunin</h1>
        <p class="mt-1 text-ink-soft">Gratis. Short link, katalog bio, dan statistik klik untuk link affiliate kamu.</p>

        <a href="{{ route('auth.google') }}" class="btn btn-secondary w-full h-11 mt-8">
            <x-icon name="google" /> Daftar dengan Google
        </a>

        <div class="my-6 flex items-center gap-3 text-[13px] text-ink-soft">
            <span class="h-px flex-1 bg-line"></span> atau pakai email <span class="h-px flex-1 bg-line"></span>
        </div>

        <form wire:submit="register" class="space-y-4">
            <div>
                <label for="name" class="field-label">Nama</label>
                <input type="text" id="name" wire:model="name" autocomplete="name" required autofocus class="field h-11">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" wire:model="email" autocomplete="email" required class="field h-11" placeholder="nama@email.com">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">Kata sandi</label>
                <input type="password" id="password" wire:model="password" autocomplete="new-password" required class="field h-11">
                @error('password') <p class="field-error">{{ $message }}</p> @else <p class="field-hint">Minimal 8 karakter.</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Ulangi kata sandi</label>
                <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" required class="field h-11">
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="register" class="btn btn-primary w-full h-11">
                <span wire:loading.remove wire:target="register">Buat akun</span>
                <span wire:loading wire:target="register">Membuat akun…</span>
            </button>
        </form>

        <p class="mt-8 text-sm text-ink-soft">Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-racun hover:underline">Masuk</a></p>
    </div>
</div>
