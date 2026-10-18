<?php

namespace Tests\Feature;

use App\Actions\Links\ExtractProductMetadataAction;
use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Catalog;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_public_catalog_loads_products_in_pages(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        Catalog::create(['user_id' => $user->id, 'username' => 'paged', 'display_name' => 'Paged', 'is_active' => true]);

        foreach (range(1, 30) as $i) {
            Link::create([
                'user_id' => $user->id,
                'title' => "Produk Nomor {$i}",
                'original_url' => "https://shopee.co.id/p{$i}",
                'slug' => "paged-{$i}",
                'status' => LinkStatus::ACTIVE,
            ]);
        }

        $component = Livewire::test(\App\Livewire\Catalog\PublicCatalog::class, ['username' => 'paged'])
            ->assertViewHas('products', fn ($products) => $products->count() === 24)
            ->assertViewHas('hasMore', true)
            ->assertViewHas('totalProducts', 30)
            ->assertSee('Muat lebih banyak produk');

        $component->call('loadMore')
            ->assertViewHas('products', fn ($products) => $products->count() === 30)
            ->assertViewHas('hasMore', false)
            ->assertDontSee('Muat lebih banyak produk');
    }

    public function test_bulk_extraction_fetches_all_urls_and_keeps_order(): void
    {
        Http::fake([
            'https://shopee.co.id/a*' => Http::response('<meta property="og:title" content="Produk A">', 200),
            'https://shopee.co.id/b*' => Http::response('<meta property="og:title" content="Produk B">', 200),
            'https://shopee.co.id/c*' => Http::response('', 500),
        ]);

        $results = (new ExtractProductMetadataAction())->executeMany([
            'https://shopee.co.id/a',
            'https://shopee.co.id/b',
            'https://shopee.co.id/c-produk-gagal',
        ]);

        Http::assertSentCount(3);
        $this->assertSame(['Produk A', 'Produk B'], [$results[0]['title'], $results[1]['title']]);
        // Failed fetch falls back to a title derived from the URL
        $this->assertSame('C Produk Gagal', $results[2]['title']);
    }

    public function test_dashboard_search_is_case_insensitive(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        Link::create([
            'user_id' => $user->id,
            'title' => 'Serum Niacinamide',
            'original_url' => 'https://shopee.co.id/serum',
            'slug' => 'serum-case',
            'status' => LinkStatus::ACTIVE,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('search', 'SERUM niacin')
            ->assertSee('Serum Niacinamide');
    }
}
