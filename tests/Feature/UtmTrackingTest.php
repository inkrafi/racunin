<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Jobs\TrackClickJob;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\LinkClickSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UtmTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeLink(): Link
    {
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        return Link::create([
            'user_id' => $user->id,
            'title' => 'Ebook Pijat Bayi',
            'original_url' => 'https://lynk.id/terapis/pijat-bayi',
            'slug' => 'pijat-bayi',
            'status' => LinkStatus::ACTIVE,
        ]);
    }

    public function test_click_stores_utm_parameters(): void
    {
        $link = $this->makeLink();

        $this->get('/pijat-bayi?utm_source=fb&utm_medium=cpc&utm_campaign=pijat-bayi&utm_content=hook-v2')
            ->assertRedirect('https://lynk.id/terapis/pijat-bayi');

        $click = LinkClick::where('link_id', $link->id)->firstOrFail();
        $this->assertSame('fb', $click->utm_source);
        $this->assertSame('cpc', $click->utm_medium);
        $this->assertSame('pijat-bayi', $click->utm_campaign);
        $this->assertSame('hook-v2', $click->utm_content);

        // Summary is still one row per link per day, untouched by UTM
        $this->assertSame(1, LinkClickSummary::where('link_id', $link->id)->count());
    }

    public function test_long_utm_values_are_truncated_and_junk_is_dropped(): void
    {
        $link = $this->makeLink();

        $this->get('/pijat-bayi?utm_content=' . str_repeat('a', 250) . '&utm_source=%20%20&utm_campaign[]=x')
            ->assertRedirect();

        $click = LinkClick::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(str_repeat('a', 100), $click->utm_content);
        $this->assertNull($click->utm_source);
        $this->assertNull($click->utm_campaign);
        $this->assertNull($click->utm_medium);
    }

    public function test_redirect_queues_utm_instead_of_writing_synchronously(): void
    {
        Queue::fake();
        $link = $this->makeLink();

        $this->get('/pijat-bayi?utm_source=fb&utm_content=hook-v2')->assertRedirect();

        Queue::assertPushed(TrackClickJob::class, fn (TrackClickJob $job) => $job->linkId === $link->id
            && $job->utm === ['source' => 'fb', 'content' => 'hook-v2']);
        $this->assertSame(0, LinkClick::count());
    }

    public function test_job_queued_before_utm_existed_still_runs(): void
    {
        $link = $this->makeLink();

        // Unserializing a payload from the previous TrackClickJob leaves $utm uninitialized
        $legacy = (new \ReflectionClass(TrackClickJob::class))->newInstanceWithoutConstructor();
        $legacy->linkId = $link->id;
        $legacy->userId = $link->user_id;
        $legacy->ipAddress = '1.2.3.4';
        $legacy->referrer = null;
        $legacy->userAgent = 'Mozilla/5.0';
        $legacy->clickedAtString = now()->format('Y-m-d H:i:s');

        app()->call([$legacy, 'handle']);

        $this->assertSame(1, LinkClick::where('link_id', $link->id)->count());
    }
}
