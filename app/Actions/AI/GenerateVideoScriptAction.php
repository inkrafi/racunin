<?php

namespace App\Actions\AI;

use App\Actions\AI\Concerns\DescribesProduct;
use App\Models\Link;
use App\Services\GeminiService;

/**
 * Writes short-form video scripts (TikTok, Reels, Shorts) scene by scene,
 * ready to film: what to show, what to say and the on-screen text.
 */
class GenerateVideoScriptAction
{
    use DescribesProduct;

    public const DURATIONS = [15, 30, 60];

    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * @return list<array{title: string, duration: int, hook: string, scenes: list<array{visual: string, voiceover: string, text_overlay: string}>, cta: string, caption: string, hashtags: list<string>}>
     */
    public function execute(Link $link, ?string $audience = null, ?string $angle = null, int $duration = 30, ?string $references = null): array
    {
        $duration = in_array($duration, self::DURATIONS, true) ? $duration : 30;
        $sceneCount = $duration === 15 ? '3' : ($duration === 30 ? '4-5' : '6-8');

        $systemInstruction = 'Anda adalah kreator konten affiliate TikTok/Reels Indonesia yang videonya sering FYP. Gaya bicara natural seperti ngobrol ke teman, bukan iklan TV.';

        $prompt = <<<PROMPT
Buatkan 2 script video pendek berdurasi sekitar {$duration} detik untuk mempromosikan produk berikut:
{$this->productBrief($link, $audience, $angle)}

{$this->referenceBlock($references)}Kedua script harus pakai format berbeda (pilih dari: review jujur, unboxing, POV/storytelling, tutorial pemakaian, alasan aku beli).
Setiap script terdiri dari {$sceneCount} scene. Hook di 3 detik pertama harus bikin orang berhenti scroll.
CTA mengarahkan ke "link di bio" atau "keranjang kuning", jangan menulis URL.

{$this->honestyRules()}

Hasilkan JSON persis dengan format:
{
  "scripts": [
    {
      "title": "nama format, misalnya Review jujur",
      "hook": "kalimat pembuka 3 detik pertama",
      "scenes": [{"visual": "apa yang direkam", "voiceover": "yang diucapkan", "text_overlay": "teks di layar, maks 6 kata"}],
      "cta": "kalimat penutup ajakan",
      "caption": "caption postingan 1-2 kalimat",
      "hashtags": ["tanpa tanda pagar, 4-6 item"]
    }
  ]
}
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction, 60);

        $scripts = [];
        foreach (is_array($result['scripts'] ?? null) ? $result['scripts'] : [] as $script) {
            if (! is_array($script)) {
                continue;
            }

            $scenes = [];
            foreach (is_array($script['scenes'] ?? null) ? $script['scenes'] : [] as $scene) {
                if (! is_array($scene) || $this->text($scene['visual'] ?? '') . $this->text($scene['voiceover'] ?? '') === '') {
                    continue;
                }
                $scenes[] = [
                    'visual' => $this->text($scene['visual'] ?? ''),
                    'voiceover' => $this->text($scene['voiceover'] ?? ''),
                    'text_overlay' => $this->text($scene['text_overlay'] ?? ''),
                ];
            }

            if ($scenes === []) {
                continue;
            }

            $scripts[] = [
                'title' => $this->text($script['title'] ?? '') ?: 'Script ' . (count($scripts) + 1),
                'duration' => $duration,
                'hook' => $this->text($script['hook'] ?? ''),
                'scenes' => array_slice($scenes, 0, 10),
                'cta' => $this->text($script['cta'] ?? ''),
                'caption' => $this->text($script['caption'] ?? ''),
                'hashtags' => array_map(fn ($tag) => ltrim($tag, '#'), $this->stringList($script['hashtags'] ?? [], 8)),
            ];
        }

        if ($scripts === []) {
            throw new \RuntimeException('AI returned no video scripts.');
        }

        return array_slice($scripts, 0, 3);
    }
}
