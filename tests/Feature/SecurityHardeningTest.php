<?php

namespace Tests\Feature;

use App\Actions\Links\ExtractProductMetadataAction;
use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Catalog;
use App\Models\Link;
use App\Models\User;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Support\AffiliateUrlPolicy;
use App\Support\VectorSearch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_url_policy_accepts_supported_marketplaces_and_detects_platform(): void
    {
        $policy = new AffiliateUrlPolicy();

        foreach ([
            'https://vt.tokopedia.com/t/ZSabc/' => 'Tokopedia',
            'https://www.tokopedia.com/toko/produk' => 'Tokopedia',
            'https://www.tiktok.com/view/product/123' => 'TikTok Shop',
            'https://s.lazada.co.id/s.abc' => 'Lazada',
            'https://www.blibli.com/p/produk/ps--1' => 'Blibli',
            'https://s.shopee.co.id/AbCd' => 'Shopee',
        ] as $url => $label) {
            $this->assertTrue($policy->isAllowed($url), $url);
            $this->assertSame($label, $policy->platformFor($url)['label'], $url);
        }

        // Affiliate networks are allowed but have no store name
        $this->assertTrue($policy->isAllowed('https://invol.co/cl123'));
        $this->assertNull($policy->platformFor('https://invol.co/cl123')['label']);

        $this->assertFalse($policy->isAllowed('https://tokopedia.com.evil.com/'));
        $this->assertNull($policy->platformFor('https://evil.com/'));
    }

    public function test_extra_domains_from_env_are_allowed_without_platform_label(): void
    {
        config(['affiliate.allowed_domains' => [...config('affiliate.allowed_domains'), 'tokoku.id']]);

        $policy = new AffiliateUrlPolicy();
        $this->assertTrue($policy->isAllowed('https://tokoku.id/produk'));
        $this->assertNull($policy->platformFor('https://tokoku.id/produk'));
    }

    public function test_catalog_buy_button_names_the_store(): void
    {
        $tokopedia = Link::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Tumbler',
            'original_url' => 'https://vt.tokopedia.com/t/abc',
            'slug' => 'tumbler-tkp',
        ]);
        $network = new Link(['original_url' => 'https://invol.co/cl1']);

        $this->assertSame('Beli di Tokopedia', $tokopedia->buy_label);
        $this->assertSame('Lihat produk', $network->buy_label);
        $this->assertSame('Lihat harga terbaru produk ini di Tokopedia.', $tokopedia->effective_og_description);
    }

    public function test_url_policy_rejects_lookalike_and_internal_hosts(): void
    {
        $policy = new AffiliateUrlPolicy();

        $this->assertTrue($policy->isAllowed('https://shopee.co.id/product/1/2'));
        $this->assertTrue($policy->isAllowed('https://s.shopee.co.id/AbCd'));
        $this->assertTrue($policy->isAllowed('https://shope.ee/xyz'));
        $this->assertTrue($policy->isAllowed('https://id.shp.ee/xyz'));

        $this->assertFalse($policy->isAllowed('https://evil.com/shopee.co.id'));
        $this->assertFalse($policy->isAllowed('https://shopee.co.id.evil.com/'));
        $this->assertFalse($policy->isAllowed('https://notshopee.co.id/'));
        $this->assertFalse($policy->isAllowed('https://user:pass@shopee.co.id/'));
        $this->assertFalse($policy->isAllowed('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse($policy->isAllowed('http://localhost:5432'));
        $this->assertFalse($policy->isAllowed('file:///etc/passwd'));
        $this->assertFalse($policy->isAllowed('javascript:alert(1)'));
    }

    public function test_extractor_refuses_to_fetch_disallowed_hosts(): void
    {
        Http::fake();

        $this->expectException(\InvalidArgumentException::class);

        try {
            (new ExtractProductMetadataAction())->execute('http://169.254.169.254/latest/meta-data');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_cannot_create_link_to_non_shopee_destination(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('title', 'Phishing')
            ->set('original_url', 'https://evil-login-page.com/shopee')
            ->set('status', 'active')
            ->call('saveLink')
            ->assertHasErrors(['original_url']);

        $this->assertDatabaseCount('links', 0);
    }

    public function test_bulk_save_skips_tampered_non_shopee_items(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\BulkImport::class)
            ->set('items', [
                ['title' => 'Valid', 'original_url' => 'https://shopee.co.id/valid', 'slug' => 'valid-bulk', 'category' => 'Produk Pilihan'],
                ['title' => 'Tampered', 'original_url' => 'https://evil.com/x', 'slug' => 'tampered-bulk', 'category' => 'Produk Pilihan'],
            ])
            ->call('save');

        $this->assertDatabaseHas('links', ['slug' => 'valid-bulk']);
        $this->assertDatabaseMissing('links', ['slug' => 'tampered-bulk']);
    }

    public function test_bulk_save_recovers_from_slug_collision(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Link::create([
            'user_id' => $user->id,
            'title' => 'Existing',
            'original_url' => 'https://shopee.co.id/existing',
            'slug' => 'taken-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\BulkImport::class)
            ->set('items', [
                ['title' => 'New Product', 'original_url' => 'https://shopee.co.id/new', 'slug' => 'taken-slug', 'category' => 'Produk Pilihan'],
            ])
            ->call('save');

        $this->assertSame(2, Link::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('links', ['title' => 'New Product']);
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $component = Livewire::test(\App\Livewire\Auth\Login::class)->set('email', $user->email);

        for ($i = 0; $i < 5; $i++) {
            $component->set('password', 'wrong-password')->call('login');
        }

        // Even the correct password is rejected while locked out
        $component->set('password', 'correct-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret', 'role' => 'admin']);

        $this->assertNull($user->role);
    }

    protected function tearDown(): void
    {
        VectorSearch::override(null);
        parent::tearDown();
    }

    public function test_public_catalog_caches_query_embeddings(): void
    {
        Config::set('gemini.api_key', 'test-gemini-key-12345');
        VectorSearch::override(true);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.01)],
            ], 200),
        ]);

        // The vector SQL itself needs pgvector; here we only verify embedding caching
        $repository = \Mockery::mock(CatalogRepositoryInterface::class);
        $repository->shouldReceive('getCatalogLinks')
            ->twice()
            ->withArgs(fn ($userId, $search, $category, $embedding) => is_array($embedding) && count($embedding) === 768)
            ->andReturn(new EloquentCollection());
        $this->app->instance(CatalogRepositoryInterface::class, $repository);

        $service = app(\App\Services\CatalogService::class);
        $service->getCatalogLinks(1, 'serum wajah', null, '10.0.0.1');
        $service->getCatalogLinks(1, 'Serum   Wajah', null, '10.0.0.1');

        // Same normalized query is embedded only once, with the key sent as a header
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-gemini-key-12345')
            && ! str_contains($request->url(), 'key='));
    }

    public function test_public_catalog_uses_keyword_search_without_pgvector(): void
    {
        Config::set('gemini.api_key', 'test-gemini-key-12345');
        VectorSearch::override(false);
        Http::fake();

        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        Catalog::create([
            'user_id' => $user->id,
            'username' => 'keyword-test',
            'display_name' => 'Keyword Test',
            'is_active' => true,
        ]);
        Link::create([
            'user_id' => $user->id,
            'title' => 'Serum Wajah Glowing',
            'original_url' => 'https://shopee.co.id/serum',
            'slug' => 'serum-keyword-test',
            'status' => LinkStatus::ACTIVE,
        ]);

        $results = app(\App\Services\CatalogService::class)->getCatalogLinks($user->id, 'Serum', null, '10.0.0.1');

        $this->assertCount(1, $results);
        Http::assertNothingSent();
    }
}
