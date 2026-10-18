<?php

namespace App\Support;

use App\Actions\Analytics\ParseUserAgentAction;

/**
 * Hands a store link from an Android in-app browser (Instagram, TikTok, ...)
 * to the store's own app, where the visitor is already signed in.
 */
class AppLink
{
    /** Webviews that keep links inside the social app instead of opening the store app. */
    public const IN_APP_BROWSERS = ['instagram', 'facebook', 'threads', 'tiktok', 'line', 'webview'];

    public function __construct(
        protected AffiliateUrlPolicy $urlPolicy,
        protected ParseUserAgentAction $parseUserAgent,
    ) {}

    /**
     * Should this visitor get the open-in-app page? $override is the ?app= query value.
     */
    public function shouldOpenInApp(?string $userAgent, ?string $override): bool
    {
        $enabled = match ($override) {
            '1' => true,
            '0' => false,
            default => (bool) config('affiliate.open_in_app'),
        };

        if (! $enabled) {
            return false;
        }

        $ua = $this->parseUserAgent->execute($userAgent);

        // intent:// only exists on Android; a forced test still needs an Android phone
        return $ua['platform'] === 'android'
            && ($override === '1' || in_array($ua['browser'], self::IN_APP_BROWSERS, true));
    }

    /**
     * Android intent URL that opens $url in the store app, or null when the store has no known app.
     * Chrome-based webviews follow S.browser_fallback_url when the app isn't installed.
     */
    public function intentUrl(string $url): ?string
    {
        $package = $this->urlPolicy->platformFor($url)['android_package'] ?? null;
        $parts = parse_url($url);

        if (! $package || empty($parts['host'])) {
            return null;
        }

        $target = $parts['host'] . ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        return 'intent://' . $target
            . '#Intent;scheme=' . ($parts['scheme'] ?? 'https')
            . ';package=' . $package
            . ';S.browser_fallback_url=' . rawurlencode($url)
            . ';end';
    }
}
