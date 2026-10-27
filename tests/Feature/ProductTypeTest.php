<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use App\Support\AffiliateUrlPolicy;
use App\Support\ProductRef;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_digital_product_link_can_be_created_and_is_typed_digital(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('title', 'Ebook Pijat Bayi')
            ->set('original_url', 'https://lynk.id/terapis/pijat-bayi?sub_id=PijatBayi-01')
            ->set('slug', '')
            ->set('status', 'active')
            ->call('saveLink')
            ->assertHasNoErrors();

        $link = Link::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(ProductType::DIGITAL, $link->product_type);
        $this->assertNull($link->product_ref);
        $this->assertSame('pijatbayi-01', $link->sub_id);
    }

    public function test_marketplace_link_keeps_marketplace_type_and_product_ref(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Serum',
            'original_url' => 'https://shopee.co.id/Serum-i.12345.67890',
            'slug' => 'serum-shopee',
        ]);

        $this->assertSame(ProductType::MARKETPLACE, $link->fresh()->product_type);
        $this->assertSame('67890', $link->product_ref);
    }

    public function test_product_type_follows_destination_url_changes(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Kelas',
            'original_url' => 'https://shopee.co.id/Kelas-i.1.2',
            'slug' => 'kelas',
        ]);

        $link->update(['original_url' => 'https://mayar.id/kelas-online']);

        $this->assertSame(ProductType::DIGITAL, $link->fresh()->product_type);
        $this->assertNull($link->fresh()->product_ref);
    }

    public function test_unknown_domains_default_to_marketplace(): void
    {
        $policy = app(AffiliateUrlPolicy::class);

        $this->assertSame(ProductType::MARKETPLACE, $policy->typeFor('https://example.com/x'));
        $this->assertSame(ProductType::MARKETPLACE, $policy->typeFor(null));
        $this->assertSame(ProductType::DIGITAL, $policy->platformFor('https://karyakarsa.com/kreator/karya')['type']);
        $this->assertTrue($policy->isAllowed('https://adcclub.id/checkout/akademi-digital-club'));
        $this->assertSame(ProductType::DIGITAL, $policy->typeFor('https://adcclub.id/checkout/akademi-digital-club'));
    }

    public function test_digital_urls_never_produce_a_product_ref(): void
    {
        // Path shaped like a Shopee item URL must not false-match on a digital storefront
        $this->assertNull(ProductRef::fromUrl('https://lynk.id/toko/Produk-i.123.456'));
    }

    public function test_system_slugs_are_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        foreach (['paket', 'health', 'u'] as $slug) {
            Livewire::actingAs($user)
                ->test(\App\Livewire\Dashboard\Overview::class)
                ->set('title', 'Produk')
                ->set('original_url', 'https://lynk.id/toko/produk')
                ->set('slug', $slug)
                ->set('status', 'active')
                ->call('saveLink')
                ->assertHasErrors(['slug']);
        }

        $this->assertSame(0, Link::count());
    }
}
