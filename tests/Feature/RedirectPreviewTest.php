<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Jobs\TrackClickJob;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedirectPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->link = Link::create([
            'user_id' => $user->id,
            'title' => 'Tas Ransel Laptop',
            'original_url' => 'https://shopee.co.id/tas-ransel',
            'slug' => 'tas-preview',
            'status' => LinkStatus::ACTIVE,
            'og_title' => 'Diskon 50% Tas Ransel Anti Air',
            'og_description' => 'Promo tas ransel premium.',
            'og_image_url' => 'https://down-id.img.susercontent.com/file/tas.jpg',
        ]);
    }

    public function test_whatsapp_crawler_gets_custom_open_graph_page_without_tracking(): void
    {
        $response = $this->withHeaders(['User-Agent' => 'WhatsApp/2.23.20.0 A'])->get('/tas-preview');

        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="Diskon 50% Tas Ransel Anti Air">', false);
        $response->assertSee('<meta property="og:image" content="https://down-id.img.susercontent.com/file/tas.jpg">', false);
        Queue::assertNotPushed(TrackClickJob::class);
    }

    public function test_facebook_crawler_gets_preview_page(): void
    {
        $this->withHeaders(['User-Agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'])
            ->get('/tas-preview')
            ->assertOk()
            ->assertSee('Promo tas ransel premium.');
    }

    public function test_in_app_browsers_used_by_humans_are_redirected_and_tracked(): void
    {
        $instagramUa = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 300.0.0.0';

        $this->withHeaders(['User-Agent' => $instagramUa])
            ->get('/tas-preview')
            ->assertRedirect('https://shopee.co.id/tas-ransel');

        Queue::assertPushed(TrackClickJob::class);
    }

    public function test_redirect_lookup_is_served_from_cache(): void
    {
        $this->get('/tas-preview')->assertRedirect('https://shopee.co.id/tas-ransel');

        DB::enableQueryLog();
        $this->get('/tas-preview')->assertRedirect('https://shopee.co.id/tas-ransel');
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "links"'));
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_cache_is_invalidated_when_link_is_deactivated_or_slug_changes(): void
    {
        $this->get('/tas-preview')->assertRedirect();

        $this->link->update(['status' => LinkStatus::INACTIVE]);
        $this->get('/tas-preview')->assertStatus(410);

        $this->link->update(['status' => LinkStatus::ACTIVE, 'slug' => 'tas-renamed']);
        $this->get('/tas-preview')->assertNotFound();
        $this->get('/tas-renamed')->assertRedirect('https://shopee.co.id/tas-ransel');
    }

    public function test_missing_slug_is_negatively_cached_until_link_is_created(): void
    {
        $this->get('/brand-new-slug')->assertNotFound();

        Link::create([
            'user_id' => $this->link->user_id,
            'title' => 'New',
            'original_url' => 'https://shopee.co.id/new',
            'slug' => 'brand-new-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        $this->get('/brand-new-slug')->assertRedirect('https://shopee.co.id/new');
    }
}
