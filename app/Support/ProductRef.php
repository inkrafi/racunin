<?php

namespace App\Support;

use App\Enums\ProductType;

/**
 * Identifiers inside marketplace URLs that also appear in commission reports,
 * used to attribute imported orders to a short link.
 */
class ProductRef
{
    /** Query parameters affiliate programs commonly use for a Sub ID, in priority order. */
    protected const SUB_ID_PARAMS = ['sub_id', 'subid', 'sub_id1', 'subid1', 'aff_sub', 'aff_sub1'];

    /**
     * Marketplace item ID from a product URL, e.g. "6101928372". Short links
     * (s.shopee.co.id, vt.tokopedia.com) carry none until they are resolved.
     */
    public static function fromUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Digital storefront paths aren't item IDs and could false-match; those links match on Sub ID only
        if (app(AffiliateUrlPolicy::class)->typeFor($url) === ProductType::DIGITAL) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        $patterns = [
            '/-i\.\d+\.(\d+)/',                  // Shopee: /Nama-Produk-i.{shop}.{item}
            '#/product/\d+/(\d+)#',              // Shopee: /product/{shop}/{item}
            '#/(?:view/)?product/(\d{6,})#',     // TikTok Shop: /view/product/{item}
            '/-i(\d+)(?:-s\d+)?\.html/',         // Lazada: /products/nama-i{item}-s{sku}.html
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Sub ID already embedded in an affiliate URL, if the program puts it in the query string.
     */
    public static function subIdFromUrl(?string $url): ?string
    {
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        foreach (self::SUB_ID_PARAMS as $param) {
            if (is_string($query[$param] ?? null) && trim($query[$param]) !== '') {
                return self::normalizeSubId($query[$param]);
            }
        }

        // Shopee affiliate links (utm_source=an_{affiliate id}) carry Sub IDs 1-5 in utm_content, joined by "-"
        if (str_starts_with((string) ($query['utm_source'] ?? ''), 'an_') && is_string($query['utm_content'] ?? null)) {
            foreach (explode('-', $query['utm_content']) as $part) {
                if (trim($part) !== '') {
                    return self::normalizeSubId($part);
                }
            }
        }

        return null;
    }

    /**
     * Case-insensitive comparison key for Sub IDs typed by users and read from reports.
     */
    public static function normalizeSubId(?string $subId): ?string
    {
        $subId = mb_strtolower(trim((string) $subId));

        return $subId === '' ? null : mb_substr($subId, 0, 100);
    }
}
