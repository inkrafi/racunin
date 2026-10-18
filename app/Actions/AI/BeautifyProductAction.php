<?php

namespace App\Actions\AI;

use App\Services\GeminiService;

class BeautifyProductAction
{
    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * Beautify raw product title and description into attractive, high-converting copy.
     *
     * @return array{title: string, description: string, og_title: string, og_description: string}
     */
    public function execute(string $rawTitle, ?string $rawDescription = null, ?string $url = null): array
    {
        $systemInstruction = "Anda adalah Copywriter E-Commerce & Affiliate Marketer profesional di Indonesia. Tugas Anda adalah mengubah judul dan deskripsi produk mentah menjadi teks promosi yang menarik, bernilai jual tinggi, mudah dibaca, dan memancing klik (CTR tinggi).";

        $prompt = <<<PROMPT
Analisis dan perindah data produk berikut untuk promosi affiliate marketplace:
- Judul Mentah: "{$rawTitle}"
- Deskripsi/Catatan Tambahan: "{$rawDescription}"
- URL Produk: "{$url}"

Hasilkan JSON persis dengan format berikut:
{
    "title": "Judul produk yang rapi, menarik, mencantumkan merek/keunggulan utama, dan emoji secukupnya (maksimal 90 karakter)",
    "description": "Deskripsi singkat persuasif 2-3 kalimat yang menonjolkan manfaat produk untuk calon pembeli",
    "og_title": "Headline judul sosial media yang bikin penasaran / clickbait elegan (maksimal 70 karakter)",
    "og_description": "Cuplikan singkat untuk preview link WhatsApp/Facebook (maksimal 140 karakter)"
}
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction);

        return [
            'title' => $result['title'] ?? $rawTitle,
            'description' => $result['description'] ?? ($rawDescription ?: ''),
            'og_title' => $result['og_title'] ?? $result['title'] ?? $rawTitle,
            'og_description' => $result['og_description'] ?? $result['description'] ?? '',
        ];
    }
}
