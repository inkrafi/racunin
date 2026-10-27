<?php

namespace App\Http\Controllers;

use App\DTOs\ClickEventData;
use App\Jobs\TrackClickJob;
use App\Jobs\TrackLandingPageViewJob;
use App\Models\Link;
use App\Support\LandingPageContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Public sales page written by the AI Agent. Buy buttons go through the short
 * link (or the tracked checkout hop below) so every visit to the store is counted.
 */
class LandingPageController extends Controller
{
    protected const CACHE_TTL = 600;
    protected const MISSING_CACHE_TTL = 60;

    public function show(Request $request, string $slug): View
    {
        $link = $this->findViewable($slug);

        // Previews and the owner's own visits shouldn't inflate the stats
        if ($link->hasPublishedLandingPage() && Auth::id() !== $link->user_id) {
            dispatch(TrackLandingPageViewJob::fromEvent($link->id, ClickEventData::fromRequest($request)));
        }

        return view('landing-page.show', [
            'link' => $link,
            'page' => LandingPageContent::normalize($link->ai_landing_page, $link),
            'preview' => ! $link->hasPublishedLandingPage(),
        ]);
    }

    /**
     * Custom checkout destination (another store link or WhatsApp): record the click, then leave.
     */
    public function go(Request $request, string $slug): RedirectResponse
    {
        $link = $this->findViewable($slug);
        $destination = LandingPageContent::normalize($link->ai_landing_page, $link)['cta_url'] ?: url($slug);

        // The owner's own preview clicks shouldn't inflate the stats
        if ($link->hasPublishedLandingPage()) {
            dispatch(TrackClickJob::fromEvent($link->id, $link->user_id, ClickEventData::fromRequest($request)));
        }

        return redirect()->away($destination, 302, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }

    /**
     * Published pages are public; unpublished ones are visible to their owner only (preview).
     */
    protected function findViewable(string $slug): Link
    {
        $link = $this->cachedLink($slug);

        abort_unless($link && ! empty($link->ai_landing_page), 404);
        abort_if(! $link->hasPublishedLandingPage() && Auth::id() !== $link->user_id, 404);

        return $link;
    }

    /**
     * Ad traffic arrives in bursts, so the row is cached (Link::booted clears it on every save).
     */
    protected function cachedLink(string $slug): ?Link
    {
        $key = Link::landingPageCacheKey($slug);

        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached ? (new Link)->newFromBuilder($cached) : null;
            }
        } catch (\Throwable $e) {
            // Cache unavailable: fall through to the database
        }

        $link = Link::where('slug', $slug)->first();

        try {
            // The embedding vector is large and unused here; [] marks a missing slug
            Cache::put($key, $link ? Arr::except($link->getAttributes(), ['embedding']) : [], $link ? self::CACHE_TTL : self::MISSING_CACHE_TTL);
        } catch (\Throwable $e) {
            // Silently ignore cache write failures
        }

        return $link;
    }
}
