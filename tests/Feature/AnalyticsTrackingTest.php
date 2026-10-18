<?php

namespace Tests\Feature;

use App\Actions\Analytics\ParseUserAgentAction;
use App\Actions\Analytics\RecordClickAction;
use App\Actions\Analytics\ResolveReferrerChannelAction;
use App\DTOs\ClickEventData;
use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Jobs\TrackClickJob;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\LinkClickSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_redirect_dispatches_track_click_job(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Test Track Product',
            'original_url' => 'https://shopee.co.id/test-product',
            'slug' => 'test-track-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15',
            'Referer' => 'https://www.tiktok.com/@creator/video/12345',
        ])->get('/test-track-slug');

        $response->assertRedirect('https://shopee.co.id/test-product');

        Queue::assertPushed(TrackClickJob::class, function ($job) use ($link, $user) {
            return $job->linkId === $link->id && $job->userId === $user->id;
        });
    }

    public function test_record_click_action_creates_raw_log_and_updates_daily_summary(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Test Direct Click Record',
            'original_url' => 'https://shopee.co.id/test-direct',
            'slug' => 'test-direct-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        $event = new ClickEventData(
            ipAddress: '202.158.45.120',
            referrer: 'https://l.instagram.com/?u=https%3A%2F%2F...',
            userAgent: 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36',
            clickedAt: now(),
        );

        $action = app(RecordClickAction::class);
        $click = $action->execute($link->id, $user->id, $event);

        $this->assertNotNull($click);
        $this->assertSame('instagram', $click->referrer_channel);
        $this->assertSame('mobile', $click->device_type);
        $this->assertSame('android', $click->platform);
        $this->assertSame('202.158.45.0', $click->ip_address);

        // Check daily summary
        $date = now()->format('Y-m-d');
        $summary = LinkClickSummary::where('link_id', $link->id)->where('date', $date)->first();
        $this->assertNotNull($summary);
        $this->assertSame(1, $summary->total_clicks);
        $this->assertSame(1, $summary->mobile_clicks);
        $this->assertSame(0, $summary->desktop_clicks);
        $this->assertSame('instagram', $summary->top_channel);
        $this->assertSame(1, $summary->channel_breakdown['instagram']);
    }

    public function test_parse_user_agent_action_accurately_detects_devices_and_browsers(): void
    {
        $parser = new ParseUserAgentAction();

        // iPhone / iOS
        $ios = $parser->execute('Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1');
        $this->assertSame('mobile', $ios['device_type']);
        $this->assertSame('ios', $ios['platform']);
        $this->assertSame('safari', $ios['browser']);

        // Android
        $android = $parser->execute('Mozilla/5.0 (Linux; Android 12; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Mobile Safari/537.36');
        $this->assertSame('mobile', $android['device_type']);
        $this->assertSame('android', $android['platform']);
        $this->assertSame('chrome', $android['browser']);

        // Desktop Windows Chrome
        $desktop = $parser->execute('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $this->assertSame('desktop', $desktop['device_type']);
        $this->assertSame('windows', $desktop['platform']);
        $this->assertSame('chrome', $desktop['browser']);

        // Bot
        $bot = $parser->execute('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
        $this->assertSame('bot', $bot['device_type']);
    }

    public function test_resolve_referrer_channel_action_categorizes_social_platforms(): void
    {
        $resolver = new ResolveReferrerChannelAction();

        $this->assertSame('tiktok', $resolver->execute('https://www.tiktok.com/@racunshopee'));
        $this->assertSame('instagram', $resolver->execute('https://l.instagram.com/?u=https%3A%2F%2F...'));
        $this->assertSame('whatsapp', $resolver->execute('https://api.whatsapp.com/send?text=...'));
        $this->assertSame('youtube', $resolver->execute('https://youtu.be/xyz123'));
        $this->assertSame('twitter', $resolver->execute('https://t.co/abcXYZ'));
        $this->assertSame('telegram', $resolver->execute('https://t.me/shopeepromo'));
        $this->assertSame('search', $resolver->execute('https://www.google.com/search?q=somethinc+serum'));
        $this->assertSame('direct', $resolver->execute(null));
        $this->assertSame('direct', $resolver->execute(''));
    }
}
