<?php

namespace App\Actions\AI;

use App\Models\ProductDiscovery;
use App\Services\GeminiService;

class DiscoverProductAction
{
    public function __construct(
        protected GeminiService $geminiService
    ) {}

    public function execute(int $userId, string $productInput): ProductDiscovery
    {
        $systemInstruction = <<<TEXT
You are an expert E-Commerce Marketer and Viral Product Analyst.
Your goal is to analyze a given product idea or name, and determine its potential for affiliate marketing.
You must return a JSON response with the following strictly defined keys:
- "uniqueness_score": Integer from 0 to 100 representing how unique and non-saturated the product is.
- "emotional_score": Integer from 0 to 100 representing how well it triggers emotional buying (e.g. FOMO, problem-solving, aesthetics).
- "viral_potential": String, strictly one of: "Rendah", "Menengah", "Tinggi".
- "target_audience": String, a brief description of the ideal buyer persona (in Indonesian).
- "pitch_angle": String, a killer copywriting angle or hook idea to sell this product on TikTok/IG (in Indonesian).
- "reasoning": String, a concise explanation for why you gave these scores (in Indonesian).
TEXT;

        $prompt = "Analisis ide produk berikut untuk affiliate marketing marketplace Indonesia (Shopee, Tokopedia, TikTok Shop, Lazada):\n\n{$productInput}";

        $result = $this->geminiService->generateJson($prompt, $systemInstruction);

        return ProductDiscovery::create([
            'user_id' => $userId,
            'product_input' => $productInput,
            'uniqueness_score' => $result['uniqueness_score'] ?? 50,
            'emotional_score' => $result['emotional_score'] ?? 50,
            'viral_potential' => $result['viral_potential'] ?? 'Menengah',
            'target_audience' => $result['target_audience'] ?? 'Pembeli online',
            'pitch_angle' => $result['pitch_angle'] ?? 'Gunakan barang ini untuk kebutuhan harian.',
            'reasoning' => $result['reasoning'] ?? 'Produk ini cukup standar.',
        ]);
    }
}
