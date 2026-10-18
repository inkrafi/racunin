<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_see_login_page(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('Masuk ke Racunin');
    }

    public function test_guest_can_see_register_page(): void
    {
        $response = $this->get(route('register'));
        $response->assertStatus(200);
        $response->assertSee('Buat akun Racunin');
    }

    public function test_guest_can_register_as_affiliate(): void
    {
        Livewire::test(\App\Livewire\Auth\Register::class)
            ->set('name', 'Affiliate Tester')
            ->set('email', 'affiliate.test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $user = User::where('email', 'affiliate.test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::AFFILIATE, $user->role);
        $this->assertTrue($user->isAffiliate());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_can_login_and_access_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::AFFILIATE,
            'password' => bcrypt('password123'),
        ]);

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Link kamu');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));
        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
