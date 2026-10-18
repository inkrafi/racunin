<?php

namespace Tests\Feature;

use App\Actions\Analytics\RecordClickAction;
use App\Actions\Analytics\ResolveReferrerChannelAction;
use App\DTOs\ClickEventData;
use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\LinkClickSummary;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36';
    protected const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    protected User $user;
    protected Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->link = Link::create([
            'user_id' => $this->user->id,
            'title' => 'Integrity Product',
            'original_url' => 'https://shopee.co.id/integrity',
            'slug' => 'integrity-slug',
            'status' => LinkStatus::ACTIVE,
        ]);
    }

    protected function click(string $ip, ?string $ua = self::ANDROID_UA, ?string $referrer = null): LinkClick
    {
        return app(RecordClickAction::class)->execute(
            $this->link->id,
            $this->user->id,
            new ClickEventData(ipAddress: $ip, referrer: $referrer, userAgent: $ua, clickedAt: now())
        );
    }

    protected function summary(): LinkClickSummary
    {
        return LinkClickSummary::where('link_id', $this->link->id)->firstOrFail();
    }

    public function test_repeat_clicks_from_same_visitor_count_once_as_unique(): void
    {
        $this->click('202.158.45.120');
        $this->click('202.158.45.120');
        $this->click('202.158.45.121'); // same /24, different visitor

        $summary = $this->summary();
        $this->assertSame(3, $summary->total_clicks);
        $this->assertSame(2, $summary->unique_clicks);
    }

    public function test_raw_ip_is_never_stored(): void
    {
        $click = $this->click('202.158.45.120');

        $this->assertSame('202.158.45.0', $click->ip_address);
        $this->assertSame(64, strlen($click->visitor_hash));
        $this->assertStringNotContainsString('202.158.45.120', $click->visitor_hash);
    }

    public function test_bots_are_logged_but_excluded_from_aggregates(): void
    {
        $this->click('31.13.115.1', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
        $this->click('66.249.66.1', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->click('202.158.45.120');

        $this->assertSame(3, LinkClick::where('link_id', $this->link->id)->count());
        $this->assertSame(1, $this->summary()->total_clicks);
    }

    public function test_summary_tracks_channels_devices_and_top_channel(): void
    {
        $this->click('1.1.1.1', self::ANDROID_UA, 'https://www.tiktok.com/@a');
        $this->click('1.1.1.2', self::ANDROID_UA, 'https://www.tiktok.com/@b');
        $this->click('1.1.1.3', self::DESKTOP_UA, 'https://l.instagram.com/?u=x');

        $summary = $this->summary();
        $this->assertSame(['instagram' => 1, 'tiktok' => 2], collect($summary->channel_breakdown)->sortKeys()->all());
        $this->assertSame('tiktok', $summary->top_channel);
        $this->assertSame(2, $summary->mobile_clicks);
        $this->assertSame(1, $summary->desktop_clicks);
    }

    public function test_in_app_browser_without_referrer_is_attributed_to_its_platform(): void
    {
        $tiktokUa = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0 Mobile Safari/537.36 trill_310203 BytedanceWebview/d8a21c6 musical_ly_2023 TikTok';

        $click = $this->click('1.1.1.1', $tiktokUa, null);

        $this->assertSame('tiktok', $click->referrer_channel);
    }

    public function test_referrer_matching_uses_domains_not_substrings(): void
    {
        $resolver = new ResolveReferrerChannelAction();

        $this->assertSame('other', $resolver->execute('https://www.microsoft.com/page'));
        $this->assertSame('other', $resolver->execute('https://www.dropbox.com/s/file'));
        $this->assertSame('other', $resolver->execute('https://www.mytiktokclone.com/'));
        $this->assertSame('twitter', $resolver->execute('https://x.com/user/status/1'));
        $this->assertSame('facebook', $resolver->execute('https://lm.facebook.com/l.php?u=x'));
        $this->assertSame('search', $resolver->execute('https://www.google.co.id/search?q=serum'));
        $this->assertSame('whatsapp', $resolver->execute('https://wa.me/6281234'));
    }

    public function test_dashboard_analytics_reads_channels_from_summaries(): void
    {
        $this->click('1.1.1.1', self::ANDROID_UA, 'https://www.tiktok.com/@a');
        $this->click('1.1.1.2', self::ANDROID_UA, 'https://youtu.be/xyz');
        $this->click('1.1.1.3', self::ANDROID_UA, 'https://www.tiktok.com/@c');

        $analytics = app(AnalyticsService::class)->getDashboardAnalytics($this->user->id);

        $this->assertSame(3, $analytics['metrics']['total_clicks']);
        $this->assertSame(3, $analytics['metrics']['unique_visitors']);
        $this->assertSame('tiktok', $analytics['metrics']['top_channel']);
        $this->assertSame(['tiktok', 'youtube'], array_column($analytics['channels'], 'channel'));
        $this->assertSame(100.0, (float) $analytics['devices']['mobile']['percentage']);
    }
}
