<?php

namespace App\Actions\AI;

use App\Actions\AI\Concerns\DescribesProduct;
use App\Models\Link;
use App\Services\GeminiService;

/**
 * Writes paid-ad copy variations for Meta (Facebook/Instagram) and TikTok Ads,
 * each built on a different selling angle so they can be A/B tested.
 */
class GenerateAdCopyAction
{
    use DescribesProduct;

    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * @return array{meta: list<array{angle: string, primary_text: string, headline: string, description: string}>, tiktok: list<array{angle: string, text: string}>}
     */
    public function execute(Link $link, ?string $audience = null, ?string $angle = null, ?string $references = null): array
    {
        $systemInstruction = 'Anda adalah media buyer dan ad copywriter Indonesia yang berpengalaman menjalankan iklan Meta Ads dan TikTok Ads untuk produk affiliate. Copy Anda punya hook kuat di kalimat pertama.';

        $prompt = <<<PROMPT
Buatkan variasi copy iklan berbayar untuk produk berikut:
{$this->productBrief($link, $audience, $angle)}

{$this->referenceBlock($references)}Buat 3 variasi Meta Ads dan 3 variasi TikTok Ads, masing-masing dengan sudut berbeda (misalnya: masalah-solusi, harga/promo, gaya hidup/aspirasi, rasa penasaran).

Batas platform:
- Meta primary_text: 2-5 kalimat, boleh emoji secukupnya, kalimat pertama adalah hook.
- Meta headline: maks 40 karakter.
- Meta description: maks 30 karakter.
- TikTok text: maks 100 karakter, tanpa hashtag berlebihan.

{$this->honestyRules()}
- Patuhi kebijakan iklan: tanpa klaim before-after, tanpa menyinggung kondisi tubuh pembaca secara negatif.

Hasilkan JSON persis dengan format:
{
  "meta": [{"angle": "nama sudut", "primary_text": "...", "headline": "...", "description": "..."}],
  "tiktok": [{"angle": "nama sudut", "text": "..."}]
}
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction, 45);

        $meta = [];
        foreach (is_array($result['meta'] ?? null) ? $result['meta'] : [] as $ad) {
            if (! is_array($ad) || $this->text($ad['primary_text'] ?? '') === '') {
                continue;
            }
            $meta[] = [
                'angle' => $this->text($ad['angle'] ?? ''),
                'primary_text' => $this->text($ad['primary_text']),
                'headline' => mb_substr($this->text($ad['headline'] ?? ''), 0, 40),
                'description' => mb_substr($this->text($ad['description'] ?? ''), 0, 30),
            ];
        }

        $tiktok = [];
        foreach (is_array($result['tiktok'] ?? null) ? $result['tiktok'] : [] as $ad) {
            if (! is_array($ad) || $this->text($ad['text'] ?? '') === '') {
                continue;
            }
            $tiktok[] = [
                'angle' => $this->text($ad['angle'] ?? ''),
                'text' => mb_substr($this->text($ad['text']), 0, 100),
            ];
        }

        if ($meta === [] && $tiktok === []) {
            throw new \RuntimeException('AI returned no ad copy.');
        }

        return ['meta' => array_slice($meta, 0, 5), 'tiktok' => array_slice($tiktok, 0, 5)];
    }
}
