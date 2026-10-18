<?php

namespace App\Actions\AI;

use App\Services\GeminiService;

class GenerateCaptionsAction
{
    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * Generate concise, high-converting social media copy for X (Twitter) and Threads.
     *
     * @return array{twitter: string, threads: string}
     */
    public function execute(
        string $title,
        ?string $description = null,
        ?float $price = null,
        ?string $discountTag = null,
        ?string $shortUrl = null,
        ?string $platform = null
    ): array {
        // Hashtag like #RacunShopee / #RacunTokopedia; generic for networks and unknown stores
        $store = $platform ?: 'marketplace';
        $hashtag = '#Racun' . ($platform ? str_replace(' ', '', $platform) : 'Belanja');

        $priceFormatted = $price ? 'Rp ' . number_format($price, 0, ',', '.') : 'Harga Promo Spesial';
        $discountInfo = $discountTag ?: 'Promo Terbatas';
        $urlInfo = $shortUrl ?: 'link di bio';

        $systemInstruction = "Anda adalah Social Media Content & Affiliate Copywriter ahli di Indonesia. Gaya copywriting Anda SANGAT SINGKAT, PADAT, TO-THE-POINT, PUNCHY, dan memancing klik instan tanpa basa-basi panjang.";

        $prompt = <<<PROMPT
Buatkan 2 variasi caption/copywriting affiliate {$store} yang SINGKAT, PADAT, dan TIDAK BERTELE-TELE untuk produk berikut:
- Produk: {$title}
- Catatan: {$description}
- Harga: {$priceFormatted}
- Promo: {$discountInfo}
- Link: {$urlInfo}

PANDUAN PANJANG (WAJIB SINGKAT):
1. twitter: Maksimal 220 karakter (Tweet punchy/curhat racun + harga + link {$urlInfo} + {$hashtag}).
2. threads: Sekitar 2-3 kalimat santai (Gaya ngobrol asik + promo + link {$urlInfo} + ajakan interaksi).

Hasilkan JSON persis dengan format berikut:
{
    "twitter": "Tweet X singkat di bawah 220 karakter dengan link",
    "threads": "Teks Threads gaya ngobrol santai dengan link"
}
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction);

        return [
            'twitter' => $result['twitter'] ?? "Nemu racun bagus banget: {$title} 😭\nLagi {$discountInfo} cuma {$priceFormatted}!\n\nLink beli: {$urlInfo}\n{$hashtag}",
            'threads' => $result['threads'] ?? "Wah gila sih {$title} cakep banget ternyata! 😍 Mumpung lagi {$discountInfo} harganya jadi {$priceFormatted} doang. Gas checkout sebelum kehabisan 👉 {$urlInfo}",
        ];
    }
}
