<?php

namespace Tests\Feature;

use App\Actions\Analytics\ParseUserAgentAction;
use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Jobs\TrackClickJob;
use App\Livewire\Dashboard\LinkTester;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OpenInAppTest extends TestCase
{
    use RefreshDatabase;

    protected const ANDROID_INSTAGRAM = 'Mozilla/5.0 (Linux; Android 14; SM-A546E Build/UP1A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/124.0.6367.82 Mobile Safari/537.36 Instagram 330.0.0.40.92 Android';
    protected const IPHONE_INSTAGRAM = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 330.0.0.21.99';
    protected const ANDROID_CHROME = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        Link::create([
            'user_id' => $this->user->id,
            'title' => 'Serum Niacinamide',
            'original_url' => 'https://shopee.co.id/Serum-i.111.222?utm_source=an_123',
            'slug' => 'serum-app',
            'status' => LinkStatus::ACTIVE,
        ]);
    }

    public function test_android_in_app_browser_gets_open_app_page_when_enabled(): void
    {
        config(['affiliate.open_in_app' => true]);

        $response = $this->withHeaders(['User-Agent' => self::ANDROID_INSTAGRAM])->get('/serum-app');

        $response->assertOk();
        $response->assertSee('intent://shopee.co.id/Serum-i.111.222?utm_source=an_123#Intent;scheme=https;package=com.shopee.id;S.browser_fallback_url=' . rawurlencode('https://shopee.co.id/Serum-i.111.222?utm_source=an_123') . ';end', false);
        $response->assertSee('Buka di aplikasi Shopee');
        $response->assertSee('Lanjut di browser');
        // The click is still counted
        Queue::assertPushed(TrackClickJob::class);
    }

    public function test_plain_redirect_is_kept_when_disabled_and_for_other_visitors(): void
    {
        config(['affiliate.open_in_app' => false]);
        $this->withHeaders(['User-Agent' => self::ANDROID_INSTAGRAM])->get('/serum-app')->assertStatus(302);

        config(['affiliate.open_in_app' => true]);
        // iOS has no intent:// and a regular browser already opens app links
        $this->withHeaders(['User-Agent' => self::IPHONE_INSTAGRAM])->get('/serum-app')->assertStatus(302);
        $this->withHeaders(['User-Agent' => self::ANDROID_CHROME])->get('/serum-app')->assertStatus(302);
    }

    public function test_query_override_allows_testing_both_variants(): void
    {
        config(['affiliate.open_in_app' => false]);
        $this->withHeaders(['User-Agent' => self::ANDROID_INSTAGRAM])->get('/serum-app?app=1')->assertOk()->assertSee('intent://', false);

        config(['affiliate.open_in_app' => true]);
        $this->withHeaders(['User-Agent' => self::ANDROID_INSTAGRAM])->get('/serum-app?app=0')->assertStatus(302);

        // Forcing still requires Android
        $this->withHeaders(['User-Agent' => self::IPHONE_INSTAGRAM])->get('/serum-app?app=1')->assertStatus(302);
    }

    public function test_store_without_android_package_falls_back_to_redirect(): void
    {
        Link::create([
            'user_id' => $this->user->id,
            'title' => 'Produk TikTok',
            'original_url' => 'https://www.tiktok.com/view/product/1729384756',
            'slug' => 'tiktok-app',
        ]);

        $this->withHeaders(['User-Agent' => self::ANDROID_INSTAGRAM])->get('/tiktok-app?app=1')
            ->assertRedirect('https://www.tiktok.com/view/product/1729384756');
    }

    public function test_user_agent_parser_recognizes_more_in_app_browsers(): void
    {
        $parser = new ParseUserAgentAction();

        $this->assertSame('threads', $parser->execute('Mozilla/5.0 (Linux; Android 14; wv) Chrome/124 Mobile Safari/537.36 Barcelona 330.0.0.29.112 Android')['browser']);
        $this->assertSame('tiktok', $parser->execute('Mozilla/5.0 (Linux; Android 13; wv) Chrome/120 Mobile Safari/537.36 trill_340204 JsSdk/1.0 BytedanceWebview/d8a21c6')['browser']);
        $this->assertSame('webview', $parser->execute('Mozilla/5.0 (Linux; Android 14; Pixel 8; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/124.0 Mobile Safari/537.36')['browser']);
        $this->assertSame('chrome', $parser->execute(self::ANDROID_CHROME)['browser']);
    }

    public function test_link_tester_shows_both_variants_for_own_links_only(): void
    {
        $other = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $foreign = Link::create([
            'user_id' => $other->id,
            'title' => 'Punya orang lain',
            'original_url' => 'https://shopee.co.id/x-i.1.2',
            'slug' => 'foreign-link',
        ]);

        Livewire::actingAs($this->user)
            ->withQueryParams(['link' => $foreign->id])
            ->test(LinkTester::class)
            ->assertSee('/serum-app?app=0')
            ->assertSee('/serum-app?app=1')
            ->assertDontSee('Punya orang lain');

        $this->actingAs($this->user)->get('/dashboard/tes-link')->assertOk()->assertSee('Tes link di HP');
    }
}
