<?php

namespace App\Actions\AI;

use App\Services\GeminiService;
use Illuminate\Support\Str;

class AutoCategorizeAction
{
    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * Auto-detect category, discount tag, and SEO slug from raw product information.
     *
     * @return array{category: string, discount_tag: string, suggested_slug: string, is_featured: bool}
     */
    public function execute(string $title, ?string $description = null, ?string $url = null): array
    {
        $systemInstruction = "Anda adalah AI E-Commerce Data Categorizer untuk platform katalog affiliate marketplace Indonesia.";

        $prompt = <<<PROMPT
Tentukan kategori standar, label diskon, dan slug yang tepat untuk produk ini:
- Judul: "{$title}"
- Deskripsi: "{$description}"
- URL: "{$url}"

Pilihan Kategori Umum di Indonesia:
- Skincare & Kecantikan
- Fashion & Pakaian
- Elektronik & Gadget
- Perlengkapan Rumah & Dapur
- Kesehatan & Kebugaran
- Makanan & Minuman
- Ibu & Bayi
- Hobi & Otomotif
- Aksesoris & Tas

Hasilkan JSON dengan format persis berikut:
{
    "category": "Nama Kategori (Pilih salah satu dari kategori umum di atas yang paling akurat)",
    "discount_tag": "Label promo singkat (misal: 'Diskon 50%!', 'Flash Sale', 'Beli 1 Gratis 1', 'Best Seller', 'Promo Spesial')",
    "suggested_slug": "slug-singkat-dan-mudah-diingat-maksimal-4-kata",
    "is_featured": true/false (apakah produk ini tergolong produk viral/hot item)
}
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction);

        return [
            'category' => $result['category'] ?? 'Lainnya',
            'discount_tag' => $result['discount_tag'] ?? 'Diskon Spesial',
            'suggested_slug' => Str::slug($result['suggested_slug'] ?? $title),
            'is_featured' => (bool) ($result['is_featured'] ?? false),
        ];
    }
}
