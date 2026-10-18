<x-layouts.app :title="$title . ' — Racunin'">
    <div class="px-4 py-16 sm:py-24">
        <div class="max-w-md mx-auto">
            <p class="text-[15px] text-ink-soft"><span class="font-bold text-ink">{{ preg_replace('#^https?://#', '', url('/')) }}/{{ $slug }}</span></p>
            <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight">{{ $title }}</h1>
            <p class="mt-2 text-ink-soft leading-relaxed">{{ $message }}</p>
            <div class="mt-8 flex flex-col sm:flex-row gap-2">
                @if (!empty($platform['home']) && !empty($platform['label']))
                    <a href="{{ $platform['home'] }}" rel="noopener" class="btn btn-primary">Buka {{ $platform['label'] }}</a>
                @endif
                <a href="{{ url('/') }}" class="btn btn-ghost">Tentang Racunin</a>
            </div>
        </div>
    </div>
</x-layouts.app>
