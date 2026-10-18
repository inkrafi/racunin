<?php

namespace App\Actions\Analytics;

class ResolveReferrerChannelAction
{
    /**
     * Channel => registrable domains. A referrer matches when its host equals a
     * domain or is a subdomain of it (so "t.co" never matches "microsoft.com").
     */
    protected const CHANNEL_DOMAINS = [
        'tiktok' => ['tiktok.com', 'tiktokv.com', 'tiktokcdn.com'],
        'instagram' => ['instagram.com'],
        'whatsapp' => ['whatsapp.com', 'whatsapp.net', 'wa.me'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'facebook' => ['facebook.com', 'fb.com', 'fb.me', 'messenger.com'],
        'twitter' => ['twitter.com', 'x.com', 't.co'],
        'telegram' => ['telegram.org', 'telegram.me', 't.me'],
        'threads' => ['threads.net', 'threads.com'],
    ];

    /** Search engines are matched on the second-level label (google.co.id, bing.com, ...). */
    protected const SEARCH_ENGINES = ['google', 'bing', 'yahoo', 'duckduckgo', 'yandex', 'baidu', 'ecosia'];

    public function execute(?string $referrer): string
    {
        if (empty($referrer)) {
            return 'direct';
        }

        $host = strtolower((string) parse_url(trim($referrer), PHP_URL_HOST));

        if ($host === '') {
            return 'other';
        }

        foreach (self::CHANNEL_DOMAINS as $channel => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    return $channel;
                }
            }
        }

        foreach (self::SEARCH_ENGINES as $engine) {
            if (preg_match('/(^|\.)' . $engine . '\.[a-z.]+$/', $host)) {
                return 'search';
            }
        }

        return 'other';
    }
}
