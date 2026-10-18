<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_link_redirects_with_302_to_shopee_url(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Skincare Redirect Test',
            'original_url' => 'https://shopee.co.id/product/999/888',
            'slug' => 'test-skincare-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        $response = $this->get('/' . $link->slug);

        $response->assertStatus(302);
        $response->assertRedirect('https://shopee.co.id/product/999/888');
    }

    public function test_inactive_link_shows_inactive_notice_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Inactive Link Test',
            'original_url' => 'https://shopee.co.id/product/inactive',
            'slug' => 'inactive-slug',
            'status' => LinkStatus::INACTIVE,
        ]);

        $response = $this->get('/' . $link->slug);

        $response->assertStatus(410);
        $response->assertSee('Link ini sedang tidak aktif');
    }

    public function test_nonexistent_slug_shows_not_found_notice(): void
    {
        $response = $this->get('/nonexistent-slug-xyz');

        $response->assertStatus(404);
        $response->assertSee('Link tidak ditemukan');
    }
}
