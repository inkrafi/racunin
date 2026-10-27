<?php

$platforms = [
    'shopee' => [
        'label' => 'Shopee',
        'home' => 'https://shopee.co.id',
        'android_package' => 'com.shopee.id',
        'type' => 'marketplace',
        'domains' => ['shopee.co.id', 'shope.ee', 'shp.ee'],
    ],
    // TikTok Shop Indonesia runs on Tokopedia; its affiliate links use vt.tokopedia.com
    'tokopedia' => [
        'label' => 'Tokopedia',
        'home' => 'https://www.tokopedia.com',
        'android_package' => 'com.tokopedia.tkpd',
        'type' => 'marketplace',
        'domains' => ['tokopedia.com', 'tokopedia.link', 'tkp.me'],
    ],
    'tiktok' => [
        'label' => 'TikTok Shop',
        'home' => 'https://www.tiktok.com',
        'android_package' => null,
        'type' => 'marketplace',
        'domains' => ['tiktok.com'],
    ],
    'lazada' => [
        'label' => 'Lazada',
        'home' => 'https://www.lazada.co.id',
        'android_package' => 'com.lazada.android',
        'type' => 'marketplace',
        'domains' => ['lazada.co.id', 'lzd.co'],
    ],
    'blibli' => [
        'label' => 'Blibli',
        'home' => 'https://www.blibli.com',
        'android_package' => 'blibli.mobile.commerce',
        'type' => 'marketplace',
        'domains' => ['blibli.com'],
    ],
    'bukalapak' => [
        'label' => 'Bukalapak',
        'home' => 'https://www.bukalapak.com',
        'android_package' => 'com.bukalapak.android',
        'type' => 'marketplace',
        'domains' => ['bukalapak.com'],
    ],
    'zalora' => [
        'label' => 'Zalora',
        'home' => 'https://www.zalora.co.id',
        'android_package' => 'com.zalora.android',
        'type' => 'marketplace',
        'domains' => ['zalora.co.id'],
    ],
    // Affiliate networks: the link lands on whichever store the campaign belongs to
    'involve' => [
        'label' => null,
        'home' => null,
        'android_package' => null,
        'type' => 'marketplace',
        'domains' => ['invol.co', 'involve.asia'],
    ],
    'accesstrade' => [
        'label' => null,
        'home' => null,
        'android_package' => null,
        'type' => 'marketplace',
        'domains' => ['atid.me', 'accesstrade.co.id'],
    ],
    // Digital product storefronts: no product IDs in the URL, conversions match on Sub ID only
    'lynk' => [
        'label' => 'Lynk.id',
        'home' => 'https://lynk.id',
        'android_package' => null,
        'type' => 'digital',
        'domains' => ['lynk.id'],
    ],
    'mayar' => [
        'label' => 'Mayar',
        'home' => 'https://mayar.id',
        'android_package' => null,
        'type' => 'digital',
        'domains' => ['mayar.id', 'mayar.link'],
    ],
    'karyakarsa' => [
        'label' => 'KaryaKarsa',
        'home' => 'https://karyakarsa.com',
        'android_package' => null,
        'type' => 'digital',
        'domains' => ['karyakarsa.com'],
    ],
    'adcclub' => [
        'label' => 'ADC Club',
        'home' => 'https://adcclub.id',
        'android_package' => null,
        'type' => 'digital',
        'domains' => ['adcclub.id'],
    ],
];

$extraDomains = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('AFFILIATE_EXTRA_DOMAINS', ''))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Marketplaces
    |--------------------------------------------------------------------------
    |
    | Each platform's label is used for "Beli di ..." buttons; a null label
    | (affiliate networks) falls back to a neutral "Lihat produk".
    |
    */

    'platforms' => $platforms,

    /*
    |--------------------------------------------------------------------------
    | Open In App (Android in-app browsers)
    |--------------------------------------------------------------------------
    |
    | Instagram, TikTok and Facebook open links in their own webview, where the
    | store's web page loads instead of its app. When enabled, Android visitors
    | in an in-app browser get a page that hands the link to the store app via
    | an intent:// URL, falling back to the web page. Append ?app=1 or ?app=0
    | to a short link to test either behaviour regardless of this setting.
    |
    */

    'open_in_app' => (bool) env('AFFILIATE_OPEN_IN_APP', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Affiliate Destination Domains
    |--------------------------------------------------------------------------
    |
    | Short links may only redirect to, and the metadata extractor may only
    | fetch from, these domains (subdomains included). This prevents the
    | shortener from being abused for phishing redirects and stops the
    | extractor from being used for SSRF against internal hosts.
    |
    | AFFILIATE_EXTRA_DOMAINS adds stores that aren't listed above.
    |
    */

    'allowed_domains' => array_values(array_unique(array_merge(
        array_merge(...array_column($platforms, 'domains')),
        $extraDomains,
    ))),
];
