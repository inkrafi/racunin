<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard\AiAgent;
use App\Models\Link;
use App\Models\User;
use App\Jobs\TrackClickJob;
use App\Support\ReferencePageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class AiAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('gemini.api_key', 'test-gemini-key-12345');
    }

    protected function fakeGemini(array $json): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]],
            ], 200),
        ]);
    }

    protected function makeLink(User $user, array $attributes = []): Link
    {
        return Link::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Serum Niacinamide',
            'original_url' => 'https://shopee.co.id/Serum-i.12345.67890',
            'slug' => 'serum-nia',
            'price' => 59000,
            'status' => LinkStatus::ACTIVE->value,
        ], $attributes));
    }

    protected function landingPageJson(): array
    {
        return [
            'headline' => 'Kulit kusam bikin nggak pede?',
            'subheadline' => 'Serum ringan untuk rutinitas harian.',
            'intro' => 'Kalau kamu capek coba ini itu, mulai dari yang simpel.',
            'pain_points' => ['Wajah kusam', '', 'Bekas jerawat'],
            'benefits' => [['title' => 'Tekstur ringan', 'desc' => 'Cepat meresap.']],
            'suitable_for' => ['Pemula skincare'],
            'faq' => [['q' => 'Aman untuk kulit sensitif?', 'a' => 'Lakukan patch test dulu.']],
            'cta_text' => 'Cek harganya',
            'closing' => 'Yuk mulai rawat kulitmu.',
        ];
    }

    public function test_generates_and_saves_landing_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user);
        $json = $this->landingPageJson();
        $json['benefits'][] = ['desc' => 'tanpa judul dibuang'];
        $this->fakeGemini($json);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->call('generateLandingPage')
            ->assertSet('error', null)
            ->assertSet('lp.headline', 'Kulit kusam bikin nggak pede?')
            ->assertSet('lp.pain_points', "Wajah kusam
Bekas jerawat")
            ->assertSet('lp.theme', 'glow');

        $page = $link->fresh()->ai_landing_page;
        $this->assertSame('Kulit kusam bikin nggak pede?', $page['headline']);
        $this->assertSame(['Wajah kusam', 'Bekas jerawat'], $page['pain_points']);
        $this->assertCount(1, $page['benefits']);
        $this->assertNull($link->fresh()->lp_published_at);
    }

    public function test_landing_page_is_only_public_once_published_and_link_active(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson()]);

        $this->get('/lp/serum-nia')->assertNotFound();
        $this->actingAs($user)->get('/lp/serum-nia')->assertOk()->assertSee('Preview: halaman ini belum online');
        auth()->logout();

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->call('togglePublish');

        $this->assertNotNull($link->fresh()->lp_published_at);

        auth()->logout();
        $this->get('/lp/serum-nia')
            ->assertOk()
            ->assertDontSee('Preview: halaman ini belum online')
            ->assertSee('Kulit kusam bikin nggak pede?')
            // Pages saved before the redesign used "intro"; it renders as the solution paragraph
            ->assertSee('Kalau kamu capek coba ini itu, mulai dari yang simpel.')
            ->assertSee('data-theme="glow"', false)
            ->assertSee('Rp 59.000')
            ->assertSee(url('serum-nia') . '?utm_source=landing_page&amp;utm_medium=racunin', false);

        $link->update(['status' => LinkStatus::INACTIVE->value]);
        $this->get('/lp/serum-nia')->assertNotFound();
    }

    public function test_generates_ad_copy_within_platform_limits(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user);
        $this->fakeGemini([
            'meta' => [['angle' => 'Hemat', 'primary_text' => 'Lagi cari serum yang ramah kantong?', 'headline' => str_repeat('a', 60), 'description' => 'Promo hari ini']],
            'tiktok' => [['angle' => 'Penasaran', 'text' => str_repeat('b', 150)], ['text' => '']],
        ]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('tab', 'ads')
            ->call('generateAdCopy')
            ->assertSet('error', null)
            ->assertSee('Lagi cari serum yang ramah kantong?');

        $ads = $link->fresh()->ai_ad_copy;
        $this->assertSame(40, mb_strlen($ads['meta'][0]['headline']));
        $this->assertCount(1, $ads['tiktok']);
        $this->assertSame(100, mb_strlen($ads['tiktok'][0]['text']));
    }

    public function test_generates_video_scripts(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user);
        $this->fakeGemini([
            'scripts' => [[
                'title' => 'Review jujur',
                'hook' => 'Jujur aku skeptis sama serum ini.',
                'scenes' => [['visual' => 'Close up botol', 'voiceover' => 'Ini dia serumnya', 'text_overlay' => 'Serum 59rb']],
                'cta' => 'Cek link di bio ya',
                'caption' => 'Serum murah yang aku pakai tiap pagi.',
                'hashtags' => ['#skincare', 'racunshopee'],
            ], ['title' => 'Tanpa scene', 'scenes' => []]],
        ]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('tab', 'script')
            ->set('duration', 15)
            ->call('generateVideoScripts')
            ->assertSet('error', null)
            ->assertSee('Jujur aku skeptis sama serum ini.');

        $scripts = $link->fresh()->ai_video_scripts;
        $this->assertCount(1, $scripts);
        $this->assertSame(15, $scripts[0]['duration']);
        $this->assertSame(['skincare', 'racunshopee'], $scripts[0]['hashtags']);
    }

    public function test_ai_failure_shows_message_and_keeps_existing_content(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson()]);
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->call('generateLandingPage')
            ->assertSet('error', 'Menulis landing page belum berhasil karena layanan AI tidak merespons. Coba lagi dalam beberapa saat.');

        $this->assertSame('Kulit kusam bikin nggak pede?', $link->fresh()->ai_landing_page['headline']);
        // The failed attempt was given back
        $this->assertSame(0, RateLimiter::attempts('ai-overview:' . $user->id));
    }

    public function test_cannot_generate_for_someone_elses_link(): void
    {
        $owner = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $intruder = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($owner);
        $this->fakeGemini($this->landingPageJson());

        Livewire::actingAs($intruder)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->call('generateLandingPage')
            ->assertForbidden();

        $this->assertNull($link->fresh()->ai_landing_page);
    }

    public function test_foreign_link_id_in_url_is_ignored_on_load(): void
    {
        $owner = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $intruder = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($owner);

        $this->actingAs($intruder)
            ->get('/dashboard/ai-agent?link=' . $link->id)
            ->assertOk()
            ->assertSee('Pilih produk untuk mulai')
            ->assertDontSee('Serum Niacinamide');
    }
    public function test_editor_saves_changes_and_shows_real_promo_countdown(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson(), 'lp_published_at' => now()]);
        $deadline = now()->setTimezone('Asia/Jakarta')->addDays(2)->format('Y-m-d\TH:i');

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('lp.theme', 'gadget')
            ->set('lp.headline', 'Headline baru')
            ->set('lp.pain_points', "Satu

Dua")
            ->set('lp.comparison_without', "Ribet")
            ->set('lp.comparison_with', "Praktis")
            ->call('addLandingItem', 'images')
            ->set('lp.images.0', 'https://cf.shopee.co.id/file/foto-2.jpg')
            ->call('addLandingItem', 'steps')
            ->set('lp.steps.0.title', 'Tuang secukupnya')
            ->set('lp.promo_ends_at', $deadline)
            ->call('saveLandingPage')
            ->assertHasNoErrors();

        $page = $link->fresh()->ai_landing_page;
        $this->assertSame('gadget', $page['theme']);
        $this->assertSame('Headline baru', $page['headline']);
        $this->assertSame(['Satu', 'Dua'], $page['pain_points']);
        $this->assertSame(['without' => ['Ribet'], 'with' => ['Praktis']], $page['comparison']);
        $this->assertSame(['https://cf.shopee.co.id/file/foto-2.jpg'], $page['images']);
        $this->assertSame('Tuang secukupnya', $page['steps'][0]['title']);

        $this->get('/lp/serum-nia')
            ->assertOk()
            ->assertSee('data-theme="gadget"', false)
            ->assertSee('Promo berakhir dalam')
            ->assertSee('https://cf.shopee.co.id/file/foto-2.jpg')
            ->assertSee('Cara pakainya')
            ->assertSee('Bedanya terasa');
    }

    public function test_editor_rejects_past_promo_and_non_http_images(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson()]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('lp.promo_ends_at', now()->setTimezone('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i'))
            ->call('addLandingItem', 'images')
            ->set('lp.images.0', 'javascript:alert(1)')
            ->call('saveLandingPage')
            ->assertHasErrors(['lp.promo_ends_at', 'lp.images.0']);

        $this->assertArrayNotHasKey('images', $link->fresh()->ai_landing_page);
    }

    public function test_page_without_promo_deadline_has_no_countdown(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson(), 'lp_published_at' => now()]);

        $this->get('/lp/serum-nia')->assertOk()->assertDontSee('Promo berakhir dalam');
    }

    public function test_reference_links_and_offer_feed_the_generator(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user);
        $this->app->instance(ReferencePageFetcher::class, new ReferencePageFetcher(fn () => ['93.184.216.34']));

        Http::fake([
            'https://ref.example.com/*' => Http::response('<html><head><title>Serum Asli</title><meta name="description" content="Kandungan niacinamide 10% dan zinc"></head><body><script>evil()</script><p>Tekstur gel ringan</p></body></html>'),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($this->landingPageJson())]]]]],
            ]),
        ]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('references', 'https://ref.example.com/serum')
            ->set('ctaText', 'Pesan via WhatsApp')
            ->set('ctaUrl', 'https://wa.me/6281234567890')
            ->set('originalPrice', '99.000')
            ->set('videoUrl', 'https://youtu.be/dQw4w9WgXcQ')
            ->call('generateLandingPage')
            ->assertHasNoErrors()
            ->assertSet('error', null);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage')) {
                return false;
            }
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Kandungan niacinamide 10% dan zinc')
                && str_contains($prompt, 'Tekstur gel ringan')
                && ! str_contains($prompt, 'evil()')
                && str_contains($prompt, 'hemat 40%')
                && str_contains($prompt, 'WhatsApp');
        });

        $page = $link->fresh()->ai_landing_page;
        $this->assertSame('Pesan via WhatsApp', $page['cta_text']);
        $this->assertSame('https://wa.me/6281234567890', $page['cta_url']);
        $this->assertSame(99000, $page['original_price']);
        $this->assertSame(['https://ref.example.com/serum'], $page['reference_urls']);
    }

    public function test_reference_fetcher_refuses_private_addresses(): void
    {
        Http::fake();
        $fetcher = new ReferencePageFetcher(fn (string $host) => $host === 'internal.example.com' ? ['10.0.0.5'] : ['93.184.216.34']);

        $this->assertNull($fetcher->fetch('http://127.0.0.1/admin'));
        $this->assertNull($fetcher->fetch('http://169.254.169.254/latest/meta-data'));
        $this->assertNull($fetcher->fetch('https://internal.example.com/'));
        $this->assertNull($fetcher->fetch('file:///etc/passwd'));
        Http::assertNothingSent();

        // A public page that redirects inward is stopped at the redirect
        Http::fake(['https://public.example.com/*' => Http::response('', 302, ['Location' => 'https://internal.example.com/secret'])]);
        $this->assertNull($fetcher->fetch('https://public.example.com/go'));
        Http::assertSentCount(1);
    }

    public function test_brief_rejects_unsupported_checkout_and_video_links(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user);
        Http::fake();

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('ctaUrl', 'https://phishing.example.com/login')
            ->set('videoUrl', 'https://vimeo.com/123')
            ->set('originalPrice', '10000')
            ->call('generateLandingPage')
            ->assertHasErrors(['ctaUrl', 'videoUrl', 'originalPrice']);

        Http::assertNothingSent();
    }

    public function test_custom_checkout_goes_through_tracked_hop(): void
    {
        Bus::fake([TrackClickJob::class]);
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $page = $this->landingPageJson() + ['cta_url' => 'https://wa.me/6281234567890', 'original_price' => 99000, 'video_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ'];
        $this->makeLink($user, ['ai_landing_page' => $page, 'lp_published_at' => now()]);

        $this->get('/lp/serum-nia')
            ->assertOk()
            ->assertSee(route('lp.go', 'serum-nia') . '?utm_source=landing_page&amp;utm_medium=racunin', false)
            ->assertSee('Rp 99.000')
            ->assertSee('-40%')
            ->assertSee('Kamu hemat Rp 40.000')
            ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/oardefault.jpg', false)
            ->assertSee('data-vertical="true"', false)
            ->assertSee('Pesan langsung lewat WhatsApp');

        $this->get('/lp/serum-nia/go?utm_source=landing_page&utm_medium=racunin')
            ->assertRedirect('https://wa.me/6281234567890');

        Bus::assertDispatched(TrackClickJob::class, fn ($job) => $job->utm === ['source' => 'landing_page', 'medium' => 'racunin']);
    }

    public function test_google_drive_video_embeds_with_chosen_shape(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson(), 'lp_published_at' => now()]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('lp.video_url', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQ/view?usp=sharing')
            ->set('lp.video_shape', 'horizontal')
            ->call('saveLandingPage')
            ->assertHasNoErrors();

        $this->get('/lp/serum-nia')
            ->assertOk()
            ->assertSee('https://drive.google.com/thumbnail?id=1AbCdEfGhIjKlMnOpQ', false)
            ->assertSee('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQ/preview', false)
            ->assertSee('data-vertical="false"', false);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('lp.video_url', 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQ')
            ->call('saveLandingPage')
            ->assertHasErrors(['lp.video_url']);
    }

    public function test_tampered_checkout_url_in_storage_is_never_followed(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson() + ['cta_url' => 'https://evil.example.com'], 'lp_published_at' => now()]);

        $this->get('/lp/serum-nia')->assertDontSee('evil.example.com');
        $this->get('/lp/serum-nia/go')->assertRedirect(url('serum-nia'));
    }

    public function test_landing_page_visits_and_buy_clicks_show_as_funnel(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson(), 'lp_published_at' => now()]);
        $browser = ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'];

        $this->withHeaders($browser)->get('/lp/serum-nia')->assertOk();
        $this->withHeaders($browser)->get('/lp/serum-nia')->assertOk();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Android 14; Mobile) Chrome/120'])->get('/lp/serum-nia')->assertOk();
        $this->withHeaders(['User-Agent' => 'facebookexternalhit/1.1'])->get('/lp/serum-nia')->assertOk();
        // The owner checking their own page isn't a visitor
        $this->actingAs($user)->withHeaders($browser)->get('/lp/serum-nia')->assertOk();
        auth()->logout();

        $this->withHeaders($browser)->get('/serum-nia?utm_source=landing_page&utm_medium=racunin');

        $this->assertDatabaseHas('landing_page_views', ['link_id' => $link->id, 'views' => 3, 'unique_views' => 2]);

        Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->assertViewHas('landingStats', ['views' => 3, 'unique_views' => 2, 'clicks' => 1, 'rate' => 33.3])
            ->assertSee('33,3%');
    }

    public function test_unpublished_preview_is_not_counted(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson()]);

        $this->actingAs($user)->get('/lp/serum-nia')->assertOk();

        $this->assertDatabaseCount('landing_page_views', 0);
    }

    public function test_public_landing_page_is_served_from_cache_until_the_link_changes(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user, ['ai_landing_page' => $this->landingPageJson(), 'lp_published_at' => now()]);
        Bus::fake();

        $this->get('/lp/serum-nia')->assertOk();

        DB::enableQueryLog();
        $this->get('/lp/serum-nia')->assertOk()->assertSee('Kulit kusam bikin nggak pede?');
        $this->assertSame([], array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], '"links"')));
        DB::disableQueryLog();

        // Every save clears the cached copy
        $link->update(['ai_landing_page' => ['headline' => 'Headline baru'] + $this->landingPageJson()]);
        $this->get('/lp/serum-nia')->assertOk()->assertSee('Headline baru');

        $link->update(['lp_published_at' => null]);
        $this->get('/lp/serum-nia')->assertNotFound();
    }

    public function test_reference_text_is_read_once_and_shared_by_all_generators(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $link = $this->makeLink($user);
        $this->app->instance(ReferencePageFetcher::class, new ReferencePageFetcher(fn () => ['93.184.216.34']));

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'ref.example.com')) {
                return Http::response('<html><body><p>Kandungan niacinamide 10% dan zinc</p></body></html>');
            }

            $prompt = $request['contents'][0]['parts'][0]['text'];

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(str_contains($prompt, 'copy iklan')
                ? ['meta' => [['angle' => 'Hemat', 'primary_text' => 'Kulit kusam?', 'headline' => 'Serum', 'description' => 'Cek']], 'tiktok' => []]
                : ['scripts' => [['title' => 'Review jujur', 'hook' => 'Jujur ya', 'scenes' => [['visual' => 'Pegang serum', 'voiceover' => 'Ini dia', 'text_overlay' => 'Serum']], 'cta' => 'Cek link di bio', 'caption' => 'Serum', 'hashtags' => ['serum']]]])]]]]]]);
        });

        $component = Livewire::actingAs($user)
            ->test(AiAgent::class)
            ->set('linkId', $link->id)
            ->set('tab', 'ads')
            ->set('references', 'https://ref.example.com/serum')
            ->call('generateAdCopy')
            ->assertSet('error', null)
            ->call('generateVideoScripts')
            ->assertSet('error', null);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'copy iklan')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Kandungan niacinamide 10% dan zinc'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'script video')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Kandungan niacinamide 10% dan zinc'));
        // The page was read once for both generators
        $this->assertCount(1, array_filter(Http::recorded()->all(), fn ($pair) => str_contains($pair[0]->url(), 'ref.example.com')));
        $this->assertSame(['https://ref.example.com/serum'], $link->fresh()->ai_reference['urls']);

        // Clearing the field stops using (and forgets) the references
        $component->set('references', '')->call('generateAdCopy')->assertSet('error', null);
        $this->assertNull($link->fresh()->ai_reference);
    }

    public function test_reference_pages_are_read_in_parallel_with_dns_pinned(): void
    {
        $fetcher = new ReferencePageFetcher(fn (string $host) => ['93.184.216.34']);
        $options = [];

        Http::fake(function ($request, $requestOptions) use (&$options) {
            $options[$request->url()] = $requestOptions['curl'][CURLOPT_RESOLVE] ?? null;

            return str_contains($request->url(), 'b.example.com')
                ? Http::response('', 500)
                : Http::response('<html><head><title>Halaman A</title></head></html>');
        });

        [$a, $b] = $fetcher->fetchMany(['https://a.example.com/p', 'https://b.example.com/p']);
        $this->assertStringContainsString('Halaman A', $a);
        $this->assertNull($b);

        // Connections go to the address that passed the private-range check, not a second lookup
        $this->assertSame(['a.example.com:443:93.184.216.34'], $options['https://a.example.com/p']);
        $this->assertSame(['b.example.com:443:93.184.216.34'], $options['https://b.example.com/p']);
    }
}
