<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

class AffiliateUrlPolicy
{
    /**
     * Check that a URL uses http(s) and points to an allowed affiliate domain.
     */
    public function isAllowed(?string $url): bool
    {
        $host = $this->safeHost($url);

        if ($host === null) {
            return false;
        }

        foreach ($this->allowedDomains() as $domain) {
            if ($this->hostMatches($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identify the marketplace a URL belongs to.
     *
     * @return array{key: string, label: ?string, home: ?string, android_package: ?string}|null Null for unknown or extra domains.
     */
    public function platformFor(?string $url): ?array
    {
        $host = $this->safeHost($url);

        if ($host === null) {
            return null;
        }

        foreach (Config::get('affiliate.platforms', []) as $key => $platform) {
            foreach ($platform['domains'] as $domain) {
                if ($this->hostMatches($host, strtolower($domain))) {
                    return [
                        'key' => $key,
                        'label' => $platform['label'],
                        'home' => $platform['home'],
                        'android_package' => $platform['android_package'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Store names for user-facing copy, e.g. "Shopee, Tokopedia, Lazada".
     *
     * @return list<string>
     */
    public function platformLabels(): array
    {
        return array_values(array_filter(array_column(Config::get('affiliate.platforms', []), 'label')));
    }

    /**
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return array_map('strtolower', Config::get('affiliate.allowed_domains', []));
    }

    /**
     * Lowercased host of an http(s) URL without embedded credentials, or null.
     */
    protected function safeHost(?string $url): ?string
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));

        // Credentials in the URL are never legitimate for a product link
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $host;
    }

    protected function hostMatches(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.' . $domain);
    }
}
