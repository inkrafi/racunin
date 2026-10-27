<?php

namespace App\Support;

use App\Models\Link;

/**
 * Shape of an AI Agent landing page (links.ai_landing_page). Both the AI output
 * and the dashboard editor go through normalize(), so the public page can trust it.
 */
class LandingPageContent
{
    /** Visual themes; the AI picks one from the product category, the user can override. */
    public const THEMES = [
        'glow' => 'Glow — kecantikan & skincare',
        'gadget' => 'Gadget — elektronik & aksesoris',
        'dapur' => 'Dapur — makanan, rumah tangga',
        'belajar' => 'Belajar — ebook, kelas, produk digital',
        'racun' => 'Racun — serba guna',
    ];

    public const MAX_IMAGES = 4;
    public const MAX_REFERENCES = 3;

    /** Drive and .mp4 links don't say how the video is shaped, so the affiliate can choose. */
    public const VIDEO_SHAPES = [
        'auto' => 'Otomatis',
        'vertical' => 'Tegak (9:16)',
        'horizontal' => 'Mendatar (16:9)',
    ];

    /** Checkout buttons may also open a WhatsApp chat (order via admin). */
    protected const CHAT_HOSTS = ['wa.me', 'api.whatsapp.com', 'whatsapp.com'];

    /** Promo deadlines typed without an offset are read as Indonesian Western time. */
    public const INPUT_TIMEZONE = 'Asia/Jakarta';

    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $data, Link $link): array
    {
        $comparison = is_array($data['comparison'] ?? null) ? $data['comparison'] : [];

        return [
            'theme' => array_key_exists($data['theme'] ?? null, self::THEMES) ? $data['theme'] : self::themeFor($link),
            'headline' => self::text($data['headline'] ?? '', 140) ?: $link->title,
            'subheadline' => self::text($data['subheadline'] ?? '', 240),
            'problem_title' => self::text($data['problem_title'] ?? '', 120),
            'pain_points' => self::list($data['pain_points'] ?? [], 5, 200),
            // "intro" is the field name of pages written before the redesign
            'solution_title' => self::text($data['solution_title'] ?? '', 120),
            'solution' => self::text($data['solution'] ?? $data['intro'] ?? '', 700),
            'benefits_title' => self::text($data['benefits_title'] ?? '', 120),
            'benefits' => self::pairs($data['benefits'] ?? [], 'title', 'desc', 6),
            'steps' => self::pairs($data['steps'] ?? [], 'title', 'desc', 5),
            'comparison' => [
                'without' => self::list($comparison['without'] ?? [], 5, 140),
                'with' => self::list($comparison['with'] ?? [], 5, 140),
            ],
            'suitable_for' => self::list($data['suitable_for'] ?? [], 6, 120),
            'faq' => self::pairs($data['faq'] ?? [], 'q', 'a', 6),
            'closing_title' => self::text($data['closing_title'] ?? '', 120),
            'closing' => self::text($data['closing'] ?? '', 400),
            'cta_text' => self::text($data['cta_text'] ?? '', 40) ?: $link->buy_label,
            'images' => self::images($data['images'] ?? []),
            'promo_ends_at' => self::futureDate($data['promo_ends_at'] ?? null),
            'cta_url' => self::isAllowedCtaUrl($url = self::text($data['cta_url'] ?? '', 2048)) ? $url : '',
            'video_url' => self::video($url = self::text($data['video_url'] ?? '', 2048)) ? $url : '',
            'video_shape' => array_key_exists($data['video_shape'] ?? null, self::VIDEO_SHAPES) ? $data['video_shape'] : 'auto',
            'original_price' => self::originalPrice($data['original_price'] ?? null, $link),
            'reference_urls' => array_slice(self::images($data['reference_urls'] ?? [], 2048), 0, self::MAX_REFERENCES),
        ];
    }

    /**
     * Checkout destination: an allowed store/affiliate domain or a WhatsApp chat.
     * Anything else would turn the landing page into an open redirect.
     */
    public static function isAllowedCtaUrl(?string $url): bool
    {
        if (! $url || ! preg_match('#^https?://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::CHAT_HOSTS as $chat) {
            if ($host === $chat || str_ends_with($host, '.' . $chat)) {
                return true;
            }
        }

        return app(AffiliateUrlPolicy::class)->isAllowed($url);
    }

    /**
     * Embeddable video from a YouTube, TikTok, Google Drive or direct .mp4 link.
     * $shape overrides the detected orientation ('auto' keeps it).
     *
     * @return array{type: 'youtube'|'tiktok'|'drive'|'file', id: ?string, src: string, vertical: bool}|null
     */
    public static function video(?string $url, string $shape = 'auto'): ?array
    {
        $video = self::detectVideo($url);

        if ($video && $shape !== 'auto') {
            $video['vertical'] = $shape === 'vertical';
        }

        return $video;
    }

    /**
     * @return array{type: 'youtube'|'tiktok'|'drive'|'file', id: ?string, src: string, vertical: bool}|null
     */
    protected static function detectVideo(?string $url): ?array
    {
        if (! $url || ! preg_match('#^https://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (preg_match('#^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return ['type' => 'youtube', 'id' => $m[1], 'src' => "https://www.youtube-nocookie.com/embed/{$m[1]}?autoplay=1&playsinline=1&rel=0", 'vertical' => str_contains($url, '/shorts/')];
        }

        if (preg_match('#^https://(?:www\.|m\.)?tiktok\.com/@[^/]+/video/(\d{8,25})#', $url, $m)) {
            return ['type' => 'tiktok', 'id' => $m[1], 'src' => "https://www.tiktok.com/player/v1/{$m[1]}?autoplay=1&rel=0", 'vertical' => true];
        }

        // Shared Drive file ("Anyone with the link"); most affiliate clips are shot on a phone, so tall by default
        if (preg_match('#^https://drive\.google\.com/(?:file/d/([A-Za-z0-9_-]{10,})|(?:open|uc)\?(?:.*&)?id=([A-Za-z0-9_-]{10,}))#', $url, $m)) {
            $id = $m[1] ?: $m[2];

            return ['type' => 'drive', 'id' => $id, 'src' => "https://drive.google.com/file/d/{$id}/preview", 'vertical' => true];
        }

        if (preg_match('#\.(mp4|webm)(\?.*)?$#i', $url)) {
            return ['type' => 'file', 'id' => null, 'src' => $url, 'vertical' => false];
        }

        return null;
    }

    /**
     * Whole-number discount from the normal price, or null without a valid one.
     */
    public static function discountPercent(array $page, Link $link): ?int
    {
        $original = $page['original_price'] ?? null;

        if (! $original || ! $link->price || $original <= (float) $link->price) {
            return null;
        }

        return (int) floor((1 - (float) $link->price / $original) * 100);
    }

    /**
     * Default theme from the link's category and title.
     */
    public static function themeFor(Link $link): string
    {
        $haystack = mb_strtolower($link->category . ' ' . $link->title);

        $keywords = [
            'glow' => ['skincare', 'kecantikan', 'beauty', 'serum', 'makeup', 'make up', 'lipstik', 'sunscreen', 'parfum', 'toner', 'moisturizer', 'facial'],
            'gadget' => ['elektronik', 'gadget', 'hp', 'earphone', 'tws', 'charger', 'laptop', 'kamera', 'speaker', 'smartwatch', 'powerbank', 'keyboard', 'mouse'],
            'dapur' => ['makanan', 'minuman', 'dapur', 'rumah', 'snack', 'kopi', 'masak', 'panci', 'blender', 'frozen', 'sambal'],
            'belajar' => ['ebook', 'kelas', 'kursus', 'course', 'buku', 'template', 'webinar', 'belajar', 'digital'],
        ];

        foreach ($keywords as $theme => $words) {
            foreach ($words as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $haystack)) {
                    return $theme;
                }
            }
        }

        return $link->product_type?->value === 'digital' ? 'belajar' : 'racun';
    }

    /**
     * Seconds left on a real promo deadline set by the affiliate, or null when there is none.
     */
    public static function promoSecondsLeft(array $page): ?int
    {
        if (empty($page['promo_ends_at'])) {
            return null;
        }

        $seconds = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($page['promo_ends_at']), false);

        return $seconds > 0 ? (int) $seconds : null;
    }

    protected static function text(mixed $value, int $max): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
    }

    /**
     * @return list<string>
     */
    protected static function list(mixed $value, int $max, int $length): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = array_filter(array_map(fn ($v) => self::text($v, $length), $value), fn ($v) => $v !== '');

        return array_slice(array_values($items), 0, $max);
    }

    /**
     * @return list<array<string, string>>
     */
    protected static function pairs(mixed $value, string $a, string $b, int $max): array
    {
        if (! is_array($value)) {
            return [];
        }

        $pairs = [];
        foreach ($value as $item) {
            if (! is_array($item) || self::text($item[$a] ?? '', 160) === '') {
                continue;
            }
            $pairs[] = [$a => self::text($item[$a], 160), $b => self::text($item[$b] ?? '', 500)];
        }

        return array_slice($pairs, 0, $max);
    }

    /**
     * @return list<string>
     */
    protected static function images(mixed $value, int $length = 2048): array
    {
        $urls = array_filter(self::list($value, self::MAX_IMAGES, $length), fn ($url) => preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL));

        return array_values($urls);
    }

    protected static function originalPrice(mixed $value, Link $link): ?int
    {
        $price = is_numeric($value = is_string($value) ? preg_replace('/[^0-9]/', '', $value) : $value) ? (int) $value : 0;

        return $price > 0 && $link->price && $price > (float) $link->price ? $price : null;
    }

    protected static function futureDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = \Illuminate\Support\Carbon::parse($value, self::INPUT_TIMEZONE);
        } catch (\Exception) {
            return null;
        }

        return $date->isFuture() ? $date->toIso8601String() : null;
    }
}
