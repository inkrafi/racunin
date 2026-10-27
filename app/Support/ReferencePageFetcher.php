<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the visible text of a reference page (product page, a competitor's sales
 * page, a review) so the AI can write from real product facts.
 *
 * The URL is user input fetched by the server, so every hop is checked against
 * private and reserved addresses (and the connection pinned to the checked
 * addresses) to keep it from reaching internal services.
 */
class ReferencePageFetcher
{
    protected const MAX_REDIRECTS = 3;
    protected const MAX_BYTES = 1_500_000;
    protected const TIMEOUT = 8;
    /** Total seconds for all pages and redirect hops together. */
    protected const TIME_BUDGET = 12;
    public const MAX_CHARS = 4000;

    /** @var callable(string): (list<string>|false) */
    protected $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? fn (string $host) => gethostbynamel($host);
    }

    /**
     * Readable text of the page, or null when it can't be fetched safely.
     */
    public function fetch(string $url): ?string
    {
        return $this->fetchMany([$url])[0];
    }

    /**
     * Reads several pages in parallel (one redirect hop per round) within one
     * shared time budget, so a few slow sites can't stall the AI request.
     *
     * @param  list<string>  $urls
     * @return list<string|null> Text per URL, in the same order
     */
    public function fetchMany(array $urls): array
    {
        $urls = array_values($urls);
        $results = array_fill(0, count($urls), null);
        $pending = $urls;
        $deadline = microtime(true) + self::TIME_BUDGET;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $pending; $hop++) {
            $targets = [];
            foreach ($pending as $i => $url) {
                if ($ips = $this->publicAddresses($url)) {
                    $targets[$i] = [$url, $ips];
                }
            }

            $timeout = min(self::TIMEOUT, $deadline - microtime(true));
            if (! $targets || $timeout < 1) {
                break;
            }

            try {
                $responses = Http::pool(function (Pool $pool) use ($targets, $timeout) {
                    foreach ($targets as $i => [$url, $ips]) {
                        $this->request($pool->as((string) $i), $url, $ips, $timeout)->get($url);
                    }
                });
            } catch (\Throwable $e) {
                Log::info('Reference page fetch failed', ['urls' => $pending, 'error' => $e->getMessage()]);
                break;
            }

            $pending = [];
            foreach ($targets as $i => [$url]) {
                $response = $responses[(string) $i] ?? null;

                if (! $response instanceof Response) {
                    Log::info('Reference page fetch failed', ['url' => $url, 'error' => $response instanceof \Throwable ? $response->getMessage() : 'no response']);
                } elseif ($response->redirect() && ($location = $response->header('Location'))) {
                    $pending[$i] = $this->absolute($location, $url);
                } elseif ($response->successful()) {
                    $results[$i] = $this->extractText(substr($response->body(), 0, self::MAX_BYTES));
                }
            }
        }

        return $results;
    }

    public function isPublicUrl(string $url): bool
    {
        return $this->publicAddresses($url) !== null;
    }

    /**
     * The URL's IP addresses when every one of them is public, otherwise null.
     *
     * @return list<string>|null
     */
    protected function publicAddresses(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host || isset($parts['user']) || isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);

        if (! $ips) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return array_values($ips);
    }

    /**
     * Pins the connection to the addresses that were just checked: without this,
     * cURL resolves the host again and a DNS-rebinding domain could answer with
     * an internal address the second time.
     *
     * @param  list<string>  $ips
     */
    protected function request(PendingRequest $request, string $url, array $ips, float $timeout): PendingRequest
    {
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        return $request
            ->timeout($timeout)
            ->connectTimeout(min(4, $timeout))
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$parts['host']}:{$port}:" . implode(',', $ips)]]])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; RacuninBot/1.0; +' . url('/') . ')',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ]);
    }

    /**
     * Meta descriptions and structured product data first (marketplaces render
     * most of the page with JavaScript), then the visible body text.
     */
    public function extractText(string $html): ?string
    {
        $chunks = [];

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $chunks[] = $m[1];
        }

        if (preg_match_all('/<meta[^>]+(?:name|property)=["\'](?:description|og:title|og:description)["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)) {
            array_push($chunks, ...$m[1]);
        }

        if (preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $json) {
                $decoded = json_decode($json, true) ?: [];
                array_walk_recursive($decoded, function ($value, $key) use (&$chunks) {
                    if (in_array($key, ['name', 'description', 'price', 'priceCurrency', 'ratingValue'], true) && is_scalar($value)) {
                        $chunks[] = "{$key}: {$value}";
                    }
                });
            }
        }

        $body = preg_replace('/<(script|style|noscript|svg|template)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $body = preg_replace('/<(br|\/p|\/div|\/li|\/h\d)[^>]*>/i', "\n", $body);
        $chunks[] = strip_tags($body);

        $text = html_entity_decode(implode("\n", $chunks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace(['/[ \t\x{00A0}]+/u', '/\s*\n\s*/'], [' ', "\n"], $text));

        return $text === '' ? null : mb_substr($text, 0, self::MAX_CHARS);
    }

    protected function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'] . '://' . $parts['host'];

        return str_starts_with($location, '/') ? $origin . $location : $origin . '/' . $location;
    }
}
