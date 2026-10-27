<?php

namespace App\Actions\AI;

use App\Actions\AI\Concerns\DescribesProduct;
use App\Models\Link;
use App\Services\GeminiService;
use App\Support\LandingPageContent;

/**
 * Writes a long-form sales page (pre-sell) for an affiliate product.
 * The page is rendered at /lp/{slug}; its buttons go through the tracked short link.
 */
class GenerateLandingPageAction
{
    use DescribesProduct;

    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * @param  string|null  $references  Text read from the affiliate's reference links
     * @param  array<string, mixed>  $settings  Page settings chosen before generating (cta_text, cta_url, original_price, video_url, reference_urls)
     * @return array<string, mixed> Normalized by LandingPageContent
     */
    public function execute(Link $link, ?string $audience = null, ?string $angle = null, ?string $references = null, array $settings = []): array
    {
        $systemInstruction = 'Anda adalah copywriter sales page direct-response terbaik di Indonesia untuk affiliate marketing. Bahasa Indonesia santai tapi meyakinkan, kalimat pendek, mudah dibaca di HP. Anda menulis seperti teman yang sudah riset produknya, bukan seperti iklan.';
        $themes = implode(', ', array_map(fn ($key, $label) => "\"{$key}\" ({$label})", array_keys(LandingPageContent::THEMES), LandingPageContent::THEMES));

        $prompt = <<<PROMPT
Buatkan isi sales page lengkap (halaman jualan sebelum pembeli diarahkan ke toko) untuk produk berikut:
{$this->productBrief($link, $audience, $angle)}

Alur: headline yang menyentuh masalah/keinginan, masalah yang dirasakan audiens, produk sebagai solusinya, manfaat, cara pakai, perbandingan tanpa vs dengan produk, cocok untuk siapa, FAQ untuk menjawab keraguan, penutup + tombol.

{$this->referenceBlock($references)}{$this->offerBlock($link, $settings)}
{$this->honestyRules()}
- Judul tiap bagian ditulis natural dan spesifik untuk produk ini, bukan judul generik seperti "Manfaat Produk".

PRINSIP HALAMAN BERKONVERSI TINGGI (WAJIB):
- Bahasa setingkat anak SD kelas 5-7: kata sehari-hari, kalimat maks 15 kata, tanpa istilah teknis yang tidak dijelaskan.
- Headline menjual hasil/manfaat utama untuk pembaca, bukan nama produk atau fitur.
- Fokus pada SATU tindakan: membeli. Jangan mengajak ke hal lain.
- Manfaat ditulis dari sudut pandang pembeli ("kamu jadi…"), bukan daftar spesifikasi.
- FAQ menjawab keraguan terbesar sebelum membeli: keaslian, cocok/tidak, cara pakai, pengiriman/retur lewat toko.
- Teks tombol spesifik dan berupa kata kerja, misalnya "Cek harga di Shopee" atau "Pesan via WhatsApp", bukan "Klik di sini".

Pilih theme yang paling cocok dari: {$themes}.

Hasilkan JSON persis dengan format:
{
  "theme": "salah satu key theme",
  "headline": "maks 12 kata",
  "subheadline": "1 kalimat pendukung",
  "problem_title": "judul bagian masalah",
  "pain_points": ["3-4 masalah yang dirasakan audiens"],
  "solution_title": "judul bagian solusi",
  "solution": "2-3 kalimat yang memperkenalkan produk sebagai jawaban",
  "benefits_title": "judul bagian manfaat",
  "benefits": [{"title": "manfaat singkat", "desc": "1-2 kalimat"}],
  "steps": [{"title": "langkah singkat", "desc": "1 kalimat"}],
  "comparison": {"without": ["3 kondisi tanpa produk"], "with": ["3 kondisi dengan produk"]},
  "suitable_for": ["3-4 tipe orang yang cocok"],
  "faq": [{"q": "pertanyaan calon pembeli", "a": "jawaban jujur singkat"}],
  "closing_title": "judul ajakan penutup",
  "closing": "1-2 kalimat ajakan penutup",
  "cta_text": "teks tombol maks 5 kata"
}
Isi benefits 4-5 item, steps 3 item (kosongkan [] kalau produk tidak perlu cara pakai), faq 3-4 item.
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction, 60);
        $previous = $link->ai_landing_page ?? [];

        // Keep what the affiliate set by hand: chosen theme, gallery, promo deadline and offer settings
        $page = LandingPageContent::normalize(array_merge($result, array_filter([
            'theme' => $previous['theme'] ?? null,
            'images' => $previous['images'] ?? null,
            'promo_ends_at' => $previous['promo_ends_at'] ?? null,
            'video_url' => $previous['video_url'] ?? null,
            'video_shape' => $previous['video_shape'] ?? null,
            'original_price' => $previous['original_price'] ?? null,
        ]), array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== [])), $link);

        if ($page['solution'] === '' && $page['benefits'] === []) {
            throw new \RuntimeException('AI returned an empty landing page.');
        }

        return $page;
    }

    protected function offerBlock(Link $link, array $settings): string
    {
        $lines = [];

        if (! empty($settings['cta_text'])) {
            $lines[] = "- Teks tombol sudah ditentukan affiliate: \"{$settings['cta_text']}\". Pakai persis teks ini untuk cta_text.";
        }
        if (! empty($settings['cta_url']) && preg_match('#(wa\.me|whatsapp\.com)#i', $settings['cta_url'])) {
            $lines[] = '- Pembeli memesan lewat chat WhatsApp, bukan checkout marketplace. Sesuaikan ajakan dan FAQ.';
        }
        $original = (int) preg_replace('/[^0-9]/', '', (string) ($settings['original_price'] ?? ''));
        if ($percent = LandingPageContent::discountPercent(['original_price' => $original], $link)) {
            $lines[] = '- Harga normal Rp ' . number_format($original, 0, ',', '.') . ", sekarang {$link->formatted_price} (hemat {$percent}%). Boleh disebut.";
        }

        return $lines ? "INFO PENAWARAN:\n" . implode("\n", $lines) . "\n\n" : '';
    }
}
