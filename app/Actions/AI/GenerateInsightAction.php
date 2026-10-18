<?php

namespace App\Actions\AI;

use App\Services\GeminiService;

class GenerateInsightAction
{
    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * Analyze traffic metrics and generate actionable selling recommendations.
     *
     * @param array $metrics Total clicks, unique visitors, top channel, etc.
     * @param array $channels Traffic breakdown by channel
     * @param array $devices Device breakdown (mobile, desktop, tablet)
     * @param array $topLinks List of top performing products
     * @param int $days Time range analyzed
     * @return array{executive_summary: string, top_insights: array<string>, best_posting_times: string, recommended_action: string}
     */
    public function execute(
        array $metrics,
        array $channels,
        array $devices,
        array $topLinks,
        int $days = 30
    ): array {
        $metricsJson = json_encode($metrics);
        $channelsJson = json_encode($channels);
        $devicesJson = json_encode($devices);
        $topLinksJson = json_encode($topLinks);

        $systemInstruction = "Anda adalah Chief Affiliate Strategist & Senior Data Growth Consultant untuk pasar e-commerce Indonesia.";

        $prompt = <<<PROMPT
Berdasarkan data performa analitik affiliate selama {$days} hari terakhir berikut:
- Ringkasan Metrik: {$metricsJson}
- Sumber Kanal (Referrer): {$channelsJson}
- Distribusi Perangkat: {$devicesJson}
- Produk Terlaris (Top Links): {$topLinksJson}

Berikan analisis data yang mendalam, tajam, dan dapat langsung dieksekusi oleh affiliate marketer untuk memaksimalkan komisi affiliate.

Hasilkan JSON dengan format persis berikut:
{
    "executive_summary": "2-3 kalimat ringkasan performa secara lugas dan memotivasi",
    "top_insights": [
        "Poin insight 1: Analisis channel mana yang paling konvertif dan mengapa",
        "Poin insight 2: Analisis preferensi perangkat pengunjung & optimasi tampilan",
        "Poin insight 3: Analisis produk yang paling banyak diminati audiens"
    ],
    "best_posting_times": "Rekomendasi jam prime-time posting harian di media sosial (misal: 11:30 - 13:00 WIB dan 19:00 - 21:00 WIB) beserta alasannya",
    "recommended_action": "1 langkah tindakan taktis prioritas tertinggi yang harus dilakukan affiliate hari ini untuk mendongkrak omset"
}
PROMPT;

        $result = $this->gemini->generateJson($prompt, $systemInstruction);

        return [
            'executive_summary' => $result['executive_summary'] ?? "Performa traffic Anda selama {$days} hari terakhir mencatatkan total {$metrics['total_clicks']} klik dengan kanal utama {$metrics['top_channel']}.",
            'top_insights' => $result['top_insights'] ?? [
                "Dominasi traffic smartphone ({$devices['mobile']['percentage']}%) menunjukkan audiens Anda aktif di mobile app.",
                "Kanal {$metrics['top_channel']} merupakan kontributor pengunjung terbesar saat ini.",
                "Pertahankan konsistensi penempatan link di bio dan story harian."
            ],
            'best_posting_times' => $result['best_posting_times'] ?? "Waktu terbaik posting: 11:30 - 13:00 WIB (jam istirahat siang) & 19:00 - 21:30 WIB (prime time malam).",
            'recommended_action' => $result['recommended_action'] ?? "Fokuskan pembuatan konten video pendek di TikTok/Reels untuk 3 produk teratas dengan link langsung di katalog bio.",
        ];
    }
}
