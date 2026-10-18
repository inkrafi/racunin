<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_can_create_link_with_auto_slug(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('title', 'Serum Shopee Promo')
            ->set('original_url', 'https://shopee.co.id/product/12345/67890')
            ->set('slug', '')
            ->set('status', 'active')
            ->call('saveLink')
            ->assertHasNoErrors();

        $link = Link::where('user_id', $user->id)->first();
        $this->assertNotNull($link);
        $this->assertSame('Serum Shopee Promo', $link->title);
        $this->assertSame('https://shopee.co.id/product/12345/67890', $link->original_url);
        $this->assertNotEmpty($link->slug);
        $this->assertSame(LinkStatus::ACTIVE, $link->status);
    }

    public function test_affiliate_can_create_link_with_custom_slug(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('title', 'Kacamata Vintage')
            ->set('original_url', 'https://shopee.co.id/product/111/222')
            ->set('slug', 'kacamata-vintage-promo')
            ->set('status', 'active')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'slug' => 'kacamata-vintage-promo',
        ]);
    }

    public function test_affiliate_cannot_use_duplicate_slug(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Link::create([
            'user_id' => $user->id,
            'title' => 'First Link',
            'original_url' => 'https://shopee.co.id/1',
            'slug' => 'unique-slug',
            'status' => LinkStatus::ACTIVE,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('title', 'Second Link')
            ->set('original_url', 'https://shopee.co.id/2')
            ->set('slug', 'unique-slug')
            ->set('status', 'active')
            ->call('saveLink')
            ->assertHasErrors(['slug']);
    }

    public function test_affiliate_can_toggle_link_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Toggle Test',
            'original_url' => 'https://shopee.co.id/1',
            'slug' => 'toggle-test',
            'status' => LinkStatus::ACTIVE,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('toggleStatus', $link->id);

        $this->assertSame(LinkStatus::INACTIVE, $link->fresh()->status);
    }

    public function test_affiliate_can_delete_their_own_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $user->id,
            'title' => 'Delete Test',
            'original_url' => 'https://shopee.co.id/delete',
            'slug' => 'to-be-deleted',
            'status' => LinkStatus::ACTIVE,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('confirmDelete', $link->id)
            ->call('deleteLink');

        $this->assertDatabaseMissing('links', ['id' => $link->id]);
    }

    public function test_admin_editing_affiliate_link_keeps_original_owner(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $affiliate = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $link = Link::create([
            'user_id' => $affiliate->id,
            'title' => 'Affiliate Link',
            'original_url' => 'https://shopee.co.id/affiliate',
            'slug' => 'affiliate-owned',
            'status' => LinkStatus::ACTIVE,
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('openEditModal', $link->id)
            ->set('title', 'Edited By Admin')
            ->call('saveLink')
            ->assertHasNoErrors();

        $link->refresh();
        $this->assertSame('Edited By Admin', $link->title);
        $this->assertSame($affiliate->id, $link->user_id);
    }

    public function test_multi_tenant_isolation_affiliate_cannot_modify_others_link(): void
    {
        $affiliateA = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $affiliateB = User::factory()->create(['role' => UserRole::AFFILIATE]);

        $linkB = Link::create([
            'user_id' => $affiliateB->id,
            'title' => 'Secret B Link',
            'original_url' => 'https://shopee.co.id/b',
            'slug' => 'link-b',
            'status' => LinkStatus::ACTIVE,
        ]);

        // Affiliate A tries to toggle Affiliate B's link -> Forbidden 403
        Livewire::actingAs($affiliateA)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('toggleStatus', $linkB->id)
            ->assertForbidden();
    }
}
