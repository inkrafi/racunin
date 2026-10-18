<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_stores_and_returns_open_graph_metadata(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Tas Ransel Laptop Waterproof',
            'original_url' => 'https://shopee.co.id/tas-ransel',
            'slug' => 'tas-ransel-promo',
            'status' => LinkStatus::ACTIVE,
            'description' => 'Tas ransel muat laptop 15.6 inch anti air',
            'og_title' => 'Diskon 50% Tas Ransel Laptop Anti Air Shopee',
            'og_description' => 'Promo tas ransel laptop premium kualitas distro.',
            'og_image_url' => 'https://example.com/tas-ransel.jpg',
            'price' => 175000,
            'discount_tag' => 'Diskon 50%',
            'category' => 'Fashion',
            'is_in_catalog' => true,
            'is_featured' => true,
        ]);

        $this->assertSame('Diskon 50% Tas Ransel Laptop Anti Air Shopee', $link->effective_og_title);
        $this->assertSame('Promo tas ransel laptop premium kualitas distro.', $link->effective_og_description);
        $this->assertSame('Rp 175.000', $link->formatted_price);
        $this->assertTrue($link->is_in_catalog);
        $this->assertTrue($link->is_featured);
    }

    public function test_open_graph_falls_back_when_custom_fields_are_null(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Smartwatch Amoled',
            'original_url' => 'https://shopee.co.id/smartwatch',
            'slug' => 'smartwatch-slug',
            'status' => LinkStatus::ACTIVE,
            'description' => 'Smartwatch layar amoled jernih',
        ]);

        $this->assertSame('Smartwatch Amoled', $link->effective_og_title);
        $this->assertSame('Smartwatch layar amoled jernih', $link->effective_og_description);
    }
}
