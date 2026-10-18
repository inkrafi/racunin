<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\LinkClickSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
    }

    public function test_affiliate_can_view_analytics_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Produk Populer Viral',
            'original_url' => 'https://shopee.co.id/viral-product',
            'slug' => 'produk-viral-dashboard',
            'status' => LinkStatus::ACTIVE,
        ]);

        LinkClickSummary::create([
            'link_id' => $link->id,
            'user_id' => $user->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 150,
            'unique_clicks' => 120,
            'mobile_clicks' => 110,
            'desktop_clicks' => 35,
            'tablet_clicks' => 5,
            'top_channel' => 'tiktok',
            'channel_breakdown' => ['tiktok' => 100, 'instagram' => 50],
        ]);

        $response = $this->actingAs($user)->get('/dashboard/analytics');
        $response->assertStatus(200);
        $response->assertSee('Statistik');
        $response->assertSee('150');
        $response->assertSee('Produk Populer Viral');
    }

    public function test_tenant_data_isolation_in_analytics(): void
    {
        $userA = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $userB = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $linkA = Link::create([
            'user_id' => $userA->id,
            'title' => 'Produk Eksklusif User A',
            'original_url' => 'https://shopee.co.id/a',
            'slug' => 'link-user-a',
            'status' => LinkStatus::ACTIVE,
        ]);

        $linkB = Link::create([
            'user_id' => $userB->id,
            'title' => 'Produk Rahasia User B',
            'original_url' => 'https://shopee.co.id/b',
            'slug' => 'link-user-b',
            'status' => LinkStatus::ACTIVE,
        ]);

        LinkClickSummary::create([
            'link_id' => $linkA->id,
            'user_id' => $userA->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 999,
            'top_channel' => 'tiktok',
        ]);

        LinkClickSummary::create([
            'link_id' => $linkB->id,
            'user_id' => $userB->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 555,
            'top_channel' => 'instagram',
        ]);

        // User A should only see 999 clicks and User A's products
        Livewire::actingAs($userA)
            ->test(\App\Livewire\Dashboard\Analytics::class)
            ->assertSee('999')
            ->assertSee('Produk Eksklusif User A')
            ->assertDontSee('555')
            ->assertDontSee('Produk Rahasia User B');
    }

    public function test_can_filter_analytics_by_link_and_days(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link1 = Link::create([
            'user_id' => $user->id,
            'title' => 'Produk Skincare',
            'original_url' => 'https://shopee.co.id/skincare',
            'slug' => 'skincare-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        $link2 = Link::create([
            'user_id' => $user->id,
            'title' => 'Produk Gadget',
            'original_url' => 'https://shopee.co.id/gadget',
            'slug' => 'gadget-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        LinkClickSummary::create([
            'link_id' => $link1->id,
            'user_id' => $user->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 200,
            'mobile_clicks' => 150,
            'desktop_clicks' => 50,
            'top_channel' => 'tiktok',
        ]);

        LinkClickSummary::create([
            'link_id' => $link2->id,
            'user_id' => $user->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 80,
            'mobile_clicks' => 60,
            'desktop_clicks' => 20,
            'top_channel' => 'youtube',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Analytics::class)
            ->assertSee('280') // Combined
            ->call('selectLink', $link1->id)
            ->assertSee('200')
            ->call('setDays', 7)
            ->assertSet('days', 7);
    }
}
