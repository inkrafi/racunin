<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->link = Link::create([
            'user_id' => $this->user->id,
            'title' => 'Tumbler Stainless',
            'original_url' => 'https://shopee.co.id/tumbler',
            'slug' => 'tumbler-studio',
            'status' => LinkStatus::ACTIVE,
        ]);
    }

    public function test_caption_studio_generates_and_persists_captions(): void
    {
        Config::set('gemini.api_key', 'test-gemini-key-12345');
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'twitter' => "Tumbler awet dingin 24 jam!\nCek: link",
                    'threads' => 'Spill tumbler favorit aku nih.',
                ])]]]]],
            ], 200),
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Dashboard\CaptionStudio::class)
            ->dispatch('open-caption-studio', linkId: $this->link->id)
            ->call('generate')
            ->assertSet('error', null)
            ->assertDispatched('links-changed')
            ->assertDispatched('toast');

        $this->assertSame('Spill tumbler favorit aku nih.', $this->link->fresh()->ai_captions['threads']);
    }

    public function test_caption_copy_button_is_safe_for_multiline_and_quoted_text(): void
    {
        $this->link->update(['ai_captions' => [
            'twitter' => "Line one\nIt's \"great\" </script>",
            'threads' => 'ok',
        ]]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Dashboard\CaptionStudio::class)
            ->dispatch('open-caption-studio', linkId: $this->link->id)
            // Newline is JSON-escaped and quotes/tags are hex-escaped, so the JS string literal stays valid
            ->assertSeeHtml('navigator.clipboard.writeText(\'Line one\\nIt\\u0027s \\u0022great\\u0022 \\u003C\\/script\\u003E\')')
            ->assertDontSeeHtml("writeText('Line one\n");
    }

    public function test_caption_studio_rejects_other_users_links(): void
    {
        $other = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Livewire::actingAs($other)
            ->test(\App\Livewire\Dashboard\CaptionStudio::class)
            ->dispatch('open-caption-studio', linkId: $this->link->id)
            ->assertForbidden();
    }

    public function test_links_list_shows_last_seven_days_of_clicks(): void
    {
        \App\Models\LinkClickSummary::create([
            'link_id' => $this->link->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'total_clicks' => 1234,
        ]);
        \App\Models\LinkClickSummary::create([
            'link_id' => $this->link->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(10)->format('Y-m-d'), // outside the 7-day window
            'total_clicks' => 999,
        ]);

        $series = app(\App\Services\LinkService::class)->getRecentClicks([$this->link->id]);
        $this->assertSame(1234, $series[$this->link->id]['total']);
        $this->assertSame(1234, $series[$this->link->id]['daily'][6]); // today is the last bar

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->assertSee('1.234'); // Indonesian thousands separator
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('openCreateModal')
            ->set('title', '')
            ->set('original_url', '')
            ->call('saveLink')
            ->assertSee('Judul wajib diisi.')
            ->assertSee('Link produk wajib diisi.');
    }

    public function test_overview_shows_toast_immediately_after_actions(): void
    {
        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->call('toggleStatus', $this->link->id)
            ->assertDispatched('toast', message: 'Link /tumbler-studio dinonaktifkan.');
    }
}
