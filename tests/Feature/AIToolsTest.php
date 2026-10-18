<?php

namespace Tests\Feature;

use App\Actions\AI\AutoCategorizeAction;
use App\Actions\AI\BeautifyProductAction;
use App\Actions\AI\GenerateCaptionsAction;
use App\Actions\AI\GenerateInsightAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AIToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('gemini.api_key', 'test-gemini-key-12345');
        Config::set('gemini.model', 'gemini-2.5-flash');
    }

    public function test_gemini_service_generate_text(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Generated text from Gemini AI']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = app(GeminiService::class);
        $this->assertTrue($service->isConfigured());

        $text = $service->generateText('Hello Gemini');
        $this->assertEquals('Generated text from Gemini AI', $text);
    }

    public function test_gemini_service_generate_json(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode(['status' => 'success', 'data' => 'AI analysis completed'])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = app(GeminiService::class);
        $result = $service->generateJson('Give me json');

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('AI analysis completed', $result['data']);
    }

    public function test_beautify_product_action(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'title' => 'Somethinc Niacinamide 10% Barrier Serum ✨',
                                    'description' => 'Serum pencerah wajah terbaik dengan 10% Niacinamide.',
                                    'og_title' => 'Viral! Serum Pencerah Tercepat di Shopee',
                                    'og_description' => 'Diskon promo serum Somethinc glowing 7 hari.'
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $action = app(BeautifyProductAction::class);
        $result = $action->execute('serum somethinc');

        $this->assertEquals('Somethinc Niacinamide 10% Barrier Serum ✨', $result['title']);
        $this->assertStringContainsString('Serum pencerah', $result['description']);
        $this->assertNotEmpty($result['og_title']);
    }

    public function test_generate_captions_action(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'tiktok' => "🔥 Stop scroll! Somethinc Serum diskon 50% cuma Rp 115.000.\n👉 Cek link bio!\n#racunshopee #shopeehaul",
                                    'instagram' => "✨ Somethinc Serum Glowing!\n💰 Rp 115.000 (Diskon 50%)\n👉 Link di bio!\n#racunshopee",
                                    'whatsapp' => "Halo kak! Somethinc Serum lagi diskon 50% cuma Rp 115.000: https://shope.ee/test",
                                    'twitter' => "Nemu racun serum Somethinc diskon 50% cuma Rp 115.000!\nLink: https://shope.ee/test #RacunShopee"
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $action = app(GenerateCaptionsAction::class);
        $captions = $action->execute(
            'Somethinc Serum',
            'Serum glowing pencerah',
            115000,
            'Diskon 50%',
            'http://localhost:8000/serum'
        );

        $this->assertArrayHasKey('twitter', $captions);
        $this->assertArrayHasKey('threads', $captions);
        $this->assertNotEmpty($captions['twitter']);
        $this->assertNotEmpty($captions['threads']);
    }

    public function test_auto_categorize_action(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'category' => 'Skincare & Kecantikan',
                                    'discount_tag' => 'Diskon 50%!',
                                    'suggested_slug' => 'serum-somethinc-glowing',
                                    'is_featured' => true
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $action = app(AutoCategorizeAction::class);
        $result = $action->execute('Somethinc Serum Glowing Niacinamide');

        $this->assertEquals('Skincare & Kecantikan', $result['category']);
        $this->assertEquals('Diskon 50%!', $result['discount_tag']);
        $this->assertEquals('serum-somethinc-glowing', $result['suggested_slug']);
        $this->assertTrue($result['is_featured']);
    }

    public function test_generate_insight_action(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'executive_summary' => 'Pertumbuhan klik Anda sangat baik didominasi traffic TikTok mobile.',
                                    'top_insights' => [
                                        'TikTok menyumbang 70% klik harian.',
                                        'Pengguna mobile smartphone mencapai 92%.',
                                        'Produk Skincare memiliki konversi tertinggi.'
                                    ],
                                    'best_posting_times' => '12:00 - 13:30 WIB & 19:30 - 21:00 WIB',
                                    'recommended_action' => 'Fokuskan video hook TikTok untuk produk serum Somethinc.'
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $action = app(GenerateInsightAction::class);
        $insight = $action->execute(
            ['total_clicks' => 500, 'unique_visitors' => 420, 'top_channel' => 'tiktok'],
            [['channel' => 'tiktok', 'percentage' => 70, 'count' => 350]],
            ['mobile' => ['percentage' => 92, 'count' => 460]],
            [['title' => 'Serum Somethinc', 'period_clicks' => 200]],
            30
        );

        $this->assertArrayHasKey('executive_summary', $insight);
        $this->assertArrayHasKey('top_insights', $insight);
        $this->assertCount(3, $insight['top_insights']);
        $this->assertArrayHasKey('best_posting_times', $insight);
        $this->assertArrayHasKey('recommended_action', $insight);
    }

    public function test_livewire_overview_ai_actions(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'title' => 'TWS Bluetooth 5.3 Waterproof Super Bass 🎧',
                                    'description' => 'Earphone nirkabel dengan audio jernih dan baterai tahan 24 jam.',
                                    'category' => 'Elektronik & Gadget',
                                    'discount_tag' => 'Flash Sale 60%!',
                                    'suggested_slug' => 'tws-super-bass',
                                    'is_featured' => true,
                                    'twitter' => 'TWS viral 90rb link: https://shope.ee #RacunShopee',
                                    'threads' => 'Rekomendasi TWS audio jernih 👉 Cek bio'
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('openCreateModal')
            ->set('title', 'tws murah')
            ->call('aiBeautifyTitle')
            ->assertSet('title', 'TWS Bluetooth 5.3 Waterproof Super Bass 🎧')
            ->call('aiAutoCategorize')
            ->assertSet('category', 'Elektronik & Gadget')
            ->assertSet('discount_tag', 'Flash Sale 60%!')
            ->call('aiGenerateCaptions')
            ->assertNotSet('generatedCaptions', []);
    }

    public function test_livewire_analytics_ai_insight(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'executive_summary' => 'Analisis AI: Traffic affiliate stabil dengan dominasi TikTok.',
                                    'top_insights' => [
                                        'TikTok memberikan CTR tertinggi',
                                        'Pengunjung mayoritas smartphone'
                                    ],
                                    'best_posting_times' => '19:00 - 21:00 WIB',
                                    'recommended_action' => 'Tambahkan variasi produk kecantikan di etalase bio.'
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Analytics::class)
            ->assertSet('aiInsight', null)
            ->call('generateAiInsight')
            ->assertNotSet('aiInsight', null);
    }

    public function test_product_discovery_tabs_and_view_detail(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $discovery = \App\Models\ProductDiscovery::create([
            'user_id' => $user->id,
            'product_input' => 'Lampu Meja Aesthetic Portable',
            'uniqueness_score' => 88,
            'emotional_score' => 92,
            'viral_potential' => 'Tinggi',
            'target_audience' => 'Mahasiswa dan pekerja WFH',
            'pitch_angle' => 'Bikin meja kerja estetik tanpa kabel ribet!',
            'reasoning' => 'Visual sangat estetik dan cocok untuk konten video TikTok.',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\ProductDiscovery::class)
            ->assertSee('Ide produk')
            ->assertSet('tab', 'analyze')
            ->call('setTab', 'history')
            ->assertSet('tab', 'history')
            ->assertSee('Lampu Meja Aesthetic Portable')
            ->assertSee('Keunikan')
            ->assertSee('88')
            ->call('viewDetail', $discovery->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Bikin meja kerja estetik tanpa kabel ribet!')
            ->assertSee('Mahasiswa dan pekerja WFH')
            ->call('closeDetailModal')
            ->assertSet('showDetailModal', false);
    }

    public function test_overview_caption_modal_view_and_generate(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = \App\Models\Link::create([
            'user_id' => $user->id,
            'title' => 'Tas Selempang Wanita Korea',
            'original_url' => 'https://shopee.co.id/tas',
            'slug' => 'tas-selempang-korea-test',
            'status' => \App\Enums\LinkStatus::ACTIVE,
            'ai_captions' => [
                'twitter' => 'Tas lucu murah banget 50rb #RacunShopee',
                'threads' => 'Spill tas selempang estetik buat kuliah.',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->assertSee('Caption AI')
            ->assertSeeLivewire(\App\Livewire\Dashboard\CaptionStudio::class);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\CaptionStudio::class)
            ->dispatch('open-caption-studio', linkId: $link->id)
            ->assertSet('show', true)
            ->assertSee('Tas lucu murah banget 50rb #RacunShopee')
            ->assertSee('Spill tas selempang estetik buat kuliah.')
            ->call('close')
            ->assertSet('show', false);
    }
}
