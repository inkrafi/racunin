<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateDailyInsightJob;
use App\Models\AiInsight;
use App\Models\Link;
use App\Models\LinkClickSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiAgentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_agent_generates_insight_only_for_users_with_traffic(): void
    {
        Config::set('gemini.api_key', 'test-gemini-key-12345');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode([
                        'executive_summary' => 'Traffic TikTok mendominasi.',
                        'top_insights' => ['A', 'B', 'C'],
                        'best_posting_times' => '19:00 - 21:00 WIB',
                        'recommended_action' => 'Posting video pendek.',
                    ])]]]],
                ],
            ], 200),
        ]);

        $activeUser = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $idleUser = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $activeUser->id,
            'title' => 'Serum Promo',
            'original_url' => 'https://shopee.co.id/serum',
            'slug' => 'serum-agent-test',
            'status' => LinkStatus::ACTIVE,
        ]);

        LinkClickSummary::create([
            'link_id' => $link->id,
            'user_id' => $activeUser->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 12,
            'mobile_clicks' => 10,
            'desktop_clicks' => 2,
            'channel_breakdown' => ['tiktok' => 12],
        ]);

        $this->artisan('affiliate:run-ai-agent')->assertSuccessful();

        // One queued job per user with traffic; idle users are not queued at all
        Queue::assertPushed(GenerateDailyInsightJob::class, fn ($job) => $job->userId === $activeUser->id);
        Queue::assertNotPushed(GenerateDailyInsightJob::class, fn ($job) => $job->userId === $idleUser->id);

        app()->call([new GenerateDailyInsightJob($activeUser->id), 'handle']);

        $insight = AiInsight::where('user_id', $activeUser->id)->first();
        $this->assertNotNull($insight);
        $this->assertSame('daily', $insight->type);
        $this->assertSame('Traffic TikTok mendominasi.', $insight->insight_data['executive_summary']);

        $this->assertDatabaseMissing('ai_insights', ['user_id' => $idleUser->id]);
    }

    public function test_agent_fails_gracefully_without_api_key(): void
    {
        Config::set('gemini.api_key', null);

        $this->artisan('affiliate:run-ai-agent')->assertFailed();

        $this->assertDatabaseCount('ai_insights', 0);
    }
}
