<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Catalog;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_public_catalog_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $catalog = Catalog::create([
            'user_id' => $user->id,
            'username' => 'test-influencer',
            'display_name' => 'Test Influencer Picks',
            'bio' => 'Rekomendasi barang aesthetic Shopee',
            'theme' => 'emerald',
            'is_active' => true,
        ]);

        $link1 = Link::create([
            'user_id' => $user->id,
            'title' => 'Serum Glowing Viral',
            'original_url' => 'https://shopee.co.id/serum-1',
            'slug' => 'serum-glowing-1',
            'status' => LinkStatus::ACTIVE,
            'price' => 99000,
            'category' => 'Skincare',
            'is_in_catalog' => true,
        ]);

        $link2 = Link::create([
            'user_id' => $user->id,
            'title' => 'Hidden Product',
            'original_url' => 'https://shopee.co.id/hidden',
            'slug' => 'hidden-link',
            'status' => LinkStatus::ACTIVE,
            'is_in_catalog' => false,
        ]);

        $response = $this->get('/@test-influencer');
        $response->assertStatus(200);
        $response->assertSee('Test Influencer Picks');
        $response->assertSee('Serum Glowing Viral');
        $response->assertSee('Rp 99.000');
        $response->assertDontSee('Hidden Product');

        // Also test alternative routes
        $this->get('/catalog/test-influencer')->assertStatus(200);
        $this->get('/u/test-influencer')->assertStatus(200);
    }

    public function test_can_filter_and_search_in_public_catalog(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $catalog = Catalog::create([
            'user_id' => $user->id,
            'username' => 'filter-tester',
            'display_name' => 'Filter Tester',
            'theme' => 'orange',
            'is_active' => true,
        ]);

        Link::create([
            'user_id' => $user->id,
            'title' => 'TWS Bluetooth Headset',
            'original_url' => 'https://shopee.co.id/tws',
            'slug' => 'tws-slug',
            'status' => LinkStatus::ACTIVE,
            'category' => 'Gadget',
            'is_in_catalog' => true,
        ]);

        Link::create([
            'user_id' => $user->id,
            'title' => 'Lipstik Matte Velvet',
            'original_url' => 'https://shopee.co.id/lipstik',
            'slug' => 'lipstik-slug',
            'status' => LinkStatus::ACTIVE,
            'category' => 'Beauty',
            'is_in_catalog' => true,
        ]);

        Livewire::test(\App\Livewire\Catalog\PublicCatalog::class, ['username' => 'filter-tester'])
            ->assertSee('TWS Bluetooth Headset')
            ->assertSee('Lipstik Matte Velvet')
            ->set('category', 'Gadget')
            ->assertSee('TWS Bluetooth Headset')
            ->assertDontSee('Lipstik Matte Velvet')
            ->set('category', 'all')
            ->set('search', 'Lipstik')
            ->assertSee('Lipstik Matte Velvet')
            ->assertDontSee('TWS Bluetooth Headset');
    }

    public function test_nonexistent_or_inactive_catalog_returns_404(): void
    {
        $response = $this->get('/@nonexistent-catalog-slug');
        $response->assertStatus(404);

        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        Catalog::create([
            'user_id' => $user->id,
            'username' => 'inactive-catalog',
            'display_name' => 'Inactive Catalog',
            'is_active' => false,
        ]);

        $this->get('/@inactive-catalog')->assertStatus(404);
    }

    public function test_affiliate_can_update_catalog_settings(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\CatalogSettings::class)
            ->set('username', 'my-custom-shop')
            ->set('display_name', 'My Custom Shop')
            ->set('bio', 'Toko affiliate terpercaya')
            ->set('theme', 'indigo')
            ->set('social_links.instagram', 'custom.shop')
            ->set('social_links.tiktok', 'customshop')
            ->call('saveCatalog')
            ->assertHasNoErrors();

        $catalog = Catalog::where('user_id', $user->id)->first();
        $this->assertNotNull($catalog);
        $this->assertSame('my-custom-shop', $catalog->username);
        $this->assertSame('My Custom Shop', $catalog->display_name);
        $this->assertSame('indigo', $catalog->theme);
        $this->assertSame('custom.shop', $catalog->social_links['instagram']);
    }

    public function test_cannot_use_duplicate_username(): void
    {
        $userA = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $userB = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Catalog::create([
            'user_id' => $userA->id,
            'username' => 'taken-username',
            'display_name' => 'User A Shop',
        ]);

        Livewire::actingAs($userB)
            ->test(\App\Livewire\Dashboard\CatalogSettings::class)
            ->set('username', 'taken-username')
            ->set('display_name', 'User B Shop')
            ->call('saveCatalog')
            ->assertHasErrors(['username']);
    }
}
