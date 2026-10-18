<?php

namespace App\Actions\Links;

use App\Support\AffiliateUrlPolicy;
use App\Support\ProductRef;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExtractProductMetadataAction
{
    protected AffiliateUrlPolicy $urlPolicy;

    public function __construct(?AffiliateUrlPolicy $urlPolicy = null)
    {
        $this->urlPolicy = $urlPolicy ?? new AffiliateUrlPolicy();
    }

    /**
     * Extract product metadata (title, image, description, category, slug) from a raw e-commerce URL.
     *
     * @return array{
     *     title: string,
     *     original_url: string,
     *     og_title: ?string,
     *     og_description: ?string,
     *     og_image_url: ?string,
     *     description: ?string,
     *     category: ?string,
     *     discount_tag: ?string,
     *     suggested_slug: string,
     *     price: ?float,
     *     product_ref: ?string,
     *     sub_id: ?string
     * }
     */
    public function execute(string $url): array
    {
        return $this->executeMany([$url])[0];
    }

    /**
     * Extract metadata for several URLs, fetching them concurrently so a bulk
     * import takes about as long as the slowest page instead of the sum of all.
     *
     * @param  list<string>  $urls
     * @return list<array> Metadata in the same order as $urls (see execute()).
     *
     * @throws \InvalidArgumentException When any URL is not an allowed affiliate URL.
     */
    public function executeMany(array $urls): array
    {
        $cleanUrls = array_values(array_map('trim', $urls));

        // Never fetch arbitrary hosts server-side (SSRF protection)
        foreach ($cleanUrls as $cleanUrl) {
            if (! $this->urlPolicy->isAllowed($cleanUrl)) {
                throw new \InvalidArgumentException('URL bukan dari toko yang didukung.');
            }
        }

        $responses = Http::pool(function ($pool) use ($cleanUrls) {
            foreach ($cleanUrls as $index => $cleanUrl) {
                $this->configureRequest($pool->as((string) $index))->get($cleanUrl);
            }
        });

        $results = [];
        foreach ($cleanUrls as $index => $cleanUrl) {
            $response = $responses[(string) $index] ?? null;
            $html = null;
            $resolvedUrl = null;

            if ($response instanceof Response && $response->successful()) {
                $html = $response->body();
                $resolvedUrl = $response->effectiveUri() ? (string) $response->effectiveUri() : null;
            } elseif ($response instanceof \Throwable) {
                Log::warning("Failed to scrape metadata for {$cleanUrl}: " . $response->getMessage());
            }

            $results[] = $this->buildMetadata($cleanUrl, $html, $resolvedUrl);
        }

        return $results;
    }

    /**
     * Browser-like headers, timeout and a redirect guard for scraping requests.
     */
    protected function configureRequest(PendingRequest $request): PendingRequest
    {
        return $request->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->withOptions([
            // Short links (shope.ee, vt.tokopedia.com, s.lazada.co.id) redirect; every hop must stay on an allowed domain
            'allow_redirects' => [
                'max' => 5,
                'protocols' => ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri) {
                    if (! $this->urlPolicy->isAllowed((string) $uri)) {
                        throw new \RuntimeException("Redirect to disallowed host blocked: {$uri->getHost()}");
                    }
                },
            ],
        ])->timeout(8);
    }

    /**
     * Build metadata from fetched HTML (or URL-based fallbacks when the fetch failed).
     */
    protected function buildMetadata(string $cleanUrl, ?string $html, ?string $resolvedUrl = null): array
    {
        $metadata = $html !== null ? $this->parseHtmlMetadata($html, $cleanUrl) : [
            'title' => '',
            'original_url' => $cleanUrl,
            'og_title' => null,
            'og_description' => null,
            'og_image_url' => null,
            'description' => null,
            'category' => null,
            'discount_tag' => null,
            'suggested_slug' => '',
            'price' => null,
        ];

        // Fallback title from URL slug if scraping didn't yield a title
        if (empty($metadata['title'])) {
            $metadata['title'] = $this->extractTitleFromUrl($cleanUrl);
        }

        // Short links only reveal the item ID and Sub ID after redirecting
        $metadata['product_ref'] = ProductRef::fromUrl($resolvedUrl) ?? ProductRef::fromUrl($cleanUrl);
        $metadata['sub_id'] = ProductRef::subIdFromUrl($cleanUrl) ?? ProductRef::subIdFromUrl($resolvedUrl);

        // Generate suggested slug if not set
        if (empty($metadata['suggested_slug'])) {
            $baseSlug = Str::slug($metadata['title']);
            $metadata['suggested_slug'] = Str::limit($baseSlug, 60, '') . '-' . Str::lower(Str::random(4));
        }

        // Auto-detect category from title/description if not detected
        if (empty($metadata['category'])) {
            $metadata['category'] = $this->inferCategory($metadata['title'] . ' ' . ($metadata['description'] ?? ''));
        }

        return $metadata;
    }

    /**
     * Parse Open Graph & meta tags from HTML string.
     */
    protected function parseHtmlMetadata(string $html, string $originalUrl): array
    {
        $ogTitle = $this->extractTagContent($html, 'og:title') ?: $this->extractTagContent($html, 'twitter:title');
        $ogImage = $this->extractTagContent($html, 'og:image') ?: $this->extractTagContent($html, 'twitter:image');
        $ogDesc = $this->extractTagContent($html, 'og:description') ?: $this->extractTagContent($html, 'description') ?: $this->extractTagContent($html, 'twitter:description');
        
        // Extract HTML <title> if og:title is missing
        $pageTitle = $ogTitle;
        if (empty($pageTitle) && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $pageTitle = trim($matches[1]);
        }

        // Clean up title (remove common e-commerce spam keywords and suffixes)
        $cleanTitle = $this->cleanProductTitle($pageTitle);

        // Detect discount / promo tags from title or description
        $discountTag = $this->detectDiscountTag($html, $pageTitle . ' ' . $ogDesc);

        // Detect price if present in metadata
        $price = $this->detectPrice($html, $ogDesc);

        $suggestedSlug = !empty($cleanTitle)
            ? Str::slug($cleanTitle) . '-' . Str::lower(Str::random(4))
            : '';

        return [
            'title' => $cleanTitle,
            'original_url' => $originalUrl,
            'og_title' => $cleanTitle,
            'og_description' => $ogDesc ? Str::limit(strip_tags($ogDesc), 200) : null,
            'og_image_url' => $ogImage,
            'description' => $ogDesc ? Str::limit(strip_tags($ogDesc), 200) : null,
            'category' => $this->inferCategory($cleanTitle . ' ' . $ogDesc),
            'discount_tag' => $discountTag,
            'suggested_slug' => Str::limit($suggestedSlug, 60, ''),
            'price' => $price,
        ];
    }

    /**
     * Extract content of a specific meta property or name.
     */
    protected function extractTagContent(string $html, string $name): ?string
    {
        // Check property="name"
        if (preg_match('/<meta[^>]+property=[\'"]' . preg_quote($name, '/') . '[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        // Check name="name"
        if (preg_match('/<meta[^>]+name=[\'"]' . preg_quote($name, '/') . '[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        // Reverse content before property/name
        if (preg_match('/<meta[^>]+content=[\'"]([^\'"]+)[\'"][^>]+(?:property|name)=[\'"]' . preg_quote($name, '/') . '[\'"]/i', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        return null;
    }

    /**
     * Clean messy e-commerce titles by removing noise words and store suffixes.
     */
    protected function cleanProductTitle(?string $rawTitle): string
    {
        if (empty($rawTitle)) {
            return '';
        }

        $title = html_entity_decode($rawTitle, ENT_QUOTES, 'UTF-8');

        // Remove store suffixes
        $title = preg_replace('/(\||-|–)\s*(Shopee|Shopee Indonesia|Tokopedia|Lazada|Lazada Indonesia|TikTok Shop|TikTok|Blibli|Blibli\.com|Bukalapak|Zalora|Zalora Indonesia).*$/i', '', $title);
        $title = preg_replace('/^Jual\s+/i', '', $title);
        $title = preg_replace('/\[(COD|PROMO|READY|ORIGINAL|DISCOUNT|MURAH|FLASH SALE|TERBARU|TERMURAH|NEW)\]/i', '', $title);
        $title = preg_replace('/【(COD|PROMO|READY|ORIGINAL|DISCOUNT|MURAH|FLASH SALE|TERBARU|TERMURAH|NEW)】/i', '', $title);
        
        // Remove excess whitespaces
        $title = trim(preg_replace('/\s+/', ' ', $title));

        return Str::limit($title, 120, '');
    }

    /**
     * Fallback title generator from URL path segments.
     */
    protected function extractTitleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_filter(explode('/', trim($path, '/')));
        
        if (empty($segments)) {
            return 'Produk pilihan';
        }

        $last = end($segments);
        // Remove IDs like -i.1234.5678 or ?sp_atk=...
        $last = preg_replace('/-i\.\d+\.\d+.*$/', '', $last);
        $last = str_replace(['-', '_'], ' ', $last);
        $last = trim(preg_replace('/\s+/', ' ', $last));

        return !empty($last) ? Str::title($last) : 'Produk pilihan';
    }

    /**
     * Detect discount tags from text content.
     */
    protected function detectDiscountTag(string $html, ?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        if (preg_match('/(\bdiskon\s*\d+%|\b\d+%\s*(?:off|diskon)|\bflash\s*sale|\bhemat\s*\d+%)/i', $text, $matches)) {
            return Str::title(trim($matches[1]));
        }

        return null;
    }

    /**
     * Detect price from metadata or schema JSON-LD.
     */
    protected function detectPrice(string $html, ?string $text): ?float
    {
        // Try Schema.org JSON-LD
        if (preg_match('/"price"\s*:\s*"?(\d+(?:\.\d+)?)"?/i', $html, $matches)) {
            $val = (float) $matches[1];
            if ($val > 500) {
                return $val;
            }
        }

        // Try Rp pattern in text
        if (!empty($text) && preg_match('/Rp\s*([0-9]{1,3}(?:\.[0-9]{3})+)/i', $text, $matches)) {
            $numeric = (float) str_replace('.', '', $matches[1]);
            if ($numeric > 0) {
                return $numeric;
            }
        }

        return null;
    }

    /**
     * Infer product category based on keyword matching.
     */
    protected function inferCategory(string $text): string
    {
        $lower = strtolower($text);

        if (preg_match('/(serum|sunscreen|toner|cream|lipstick|skincare|facial|parfum|bodyserum|acne|glow|moisturizer|lip balm|makeup)/i', $lower)) {
            return 'Skincare & Kecantikan';
        }

        if (preg_match('/(baju|kaos|kemeja|celana|rok|dress|jaket|hoodie|sepatu|sandal|tas|dompet|hijab|gamis|outfit|cardigan|sneakers)/i', $lower)) {
            return 'Fashion & Pakaian';
        }

        if (preg_match('/(tws|earphone|headset|charger|kabel|powerbank|casing|case|mouse|keyboard|speaker|smartwatch|hp|handphone|gadget)/i', $lower)) {
            return 'Elektronik & Gadget';
        }

        if (preg_match('/(lampu|meja|kursi|sprei|selimut|bantal|sapu|pel|rak|dapur|wajan|panci|botol|tumbler|storage|dekorasi)/i', $lower)) {
            return 'Rumah Tangga & Dekor';
        }

        if (preg_match('/(snack|kripik|keripik|coklat|kopi|teh|sambal|makanan|minuman|biskuit|mie)/i', $lower)) {
            return 'Makanan & Minuman';
        }

        return 'Produk Pilihan';
    }
}
