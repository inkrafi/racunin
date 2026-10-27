<?php

namespace App\Http\Controllers;

use App\DTOs\ClickEventData;
use App\Enums\LinkStatus;
use App\Jobs\TrackClickJob;
use App\Services\LinkService;
use App\Support\AffiliateUrlPolicy;
use App\Support\AppLink;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectController extends Controller
{
    /**
     * Crawlers that fetch a URL to render a chat/social link preview. They get an
     * Open Graph page with the affiliate's custom branding instead of a redirect
     * (which would make them scrape the store's own metadata).
     */
    /**
     * Crawler-only signatures; in-app browsers used by humans (Instagram, TikTok,
     * LINE, Pinterest app, KakaoTalk) must NOT match or their clicks would be lost.
     */
    protected const LINK_PREVIEW_BOTS = '/facebookexternalhit|facebot|whatsapp\/|twitterbot|telegrambot|slackbot|discordbot|linkedinbot|pinterestbot|skypeuripreview|kakaotalk-scrap|vkshare|redditbot|embedly|iframely/i';

    public function __construct(
        protected LinkService $linkService,
        protected AffiliateUrlPolicy $urlPolicy,
        protected AppLink $appLink,
    ) {}

    public function handle(Request $request, string $slug): Response
    {
        // Single cached lookup covers active, inactive and missing slugs
        $link = $this->linkService->resolveSlug($slug);

        if (! $link) {
            return response()->view('redirects.inactive', [
                'title' => 'Link tidak ditemukan',
                'message' => 'Mungkin ada salah ketik, atau pemiliknya sudah menghapus link ini.',
                'slug' => $slug,
                'platform' => null,
            ], 404);
        }

        if ($link['status'] !== LinkStatus::ACTIVE->value) {
            $platform = $this->urlPolicy->platformFor($link['original_url']);

            return response()->view('redirects.inactive', [
                'title' => 'Link ini sedang tidak aktif',
                'message' => 'Pemilik link menonaktifkannya untuk sementara. Produknya mungkin masih tersedia di '
                    . ($platform['label'] ?? 'tokonya') . '.',
                'slug' => $slug,
                'platform' => $platform,
            ], 410);
        }

        // Link preview crawlers: serve custom OG metadata, don't count as a click
        if ($this->isLinkPreviewBot($request->userAgent())) {
            return response()->view('redirects.preview', [
                'link' => $link,
                'shortUrl' => url($slug),
            ])->header('Cache-Control', 'public, max-age=300');
        }

        // Asynchronously dispatch click tracking to queue (zero redirect latency)
        dispatch(TrackClickJob::fromEvent($link['id'], $link['user_id'], ClickEventData::fromRequest($request)));

        // Android in-app browsers: try the store app first, the web page is the fallback
        $appOverride = $request->query('app');

        if ($this->appLink->shouldOpenInApp($request->userAgent(), is_string($appOverride) ? $appOverride : null)
            && ($intentUrl = $this->appLink->intentUrl($link['original_url']))) {
            return response()->view('redirects.open-app', [
                'link' => $link,
                'intentUrl' => $intentUrl,
                'platform' => $this->urlPolicy->platformFor($link['original_url']),
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        // Standard HTTP 302 Found redirect
        return redirect()->away($link['original_url'], 302, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    protected function isLinkPreviewBot(?string $userAgent): bool
    {
        return ! empty($userAgent) && preg_match(self::LINK_PREVIEW_BOTS, $userAgent) === 1;
    }
}
