<?php

namespace Database\Seeders;

use App\Enums\LinkStatus;
use App\Enums\UserRole;
use App\Models\Catalog;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\LinkClickSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create or update Default Administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@affiliate.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123'),
                'role' => UserRole::ADMIN,
            ]
        );

        // 2. Create or update Demo Affiliate
        $affiliate = User::firstOrCreate(
            ['email' => 'affiliate@affiliate.com'],
            [
                'name' => 'Demo Affiliate Partner',
                'password' => Hash::make('password123'),
                'role' => UserRole::AFFILIATE,
            ]
        );

        // 3. Create or update Demo Catalog Profile
        Catalog::updateOrCreate(
            ['user_id' => $affiliate->id],
            [
                'username' => 'demo-affiliate',
                'display_name' => 'Racun Shopee Pilihan Demo',
                'bio' => 'Koleksi rekomendasi skincare viral, gadget hemat & barang unik Shopee dengan rating 4.9+ bintang.',
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&auto=format&fit=crop&q=80',
                'theme' => 'ramuan',
                'social_links' => [
                    'instagram' => 'demo.affiliate',
                    'tiktok' => 'demoaffiliate.shopee',
                    'whatsapp' => '081234567890',
                    'telegram' => 'racunshopeedemo',
                ],
                'is_active' => true,
            ]
        );

        // 4. Create Sample Links for Demo Affiliate with Phase 2 attributes
        $sampleLinks = [
            [
                'title' => 'Serum Niacinamide 10% Somethinc',
                'original_url' => 'https://shopee.co.id/Somethinc-Niacinamide-Moisture-Sabi-Beet-Maxi-Ampoule-i.187313328.6101928372',
                'slug' => 'somethinc-serum',
                'status' => LinkStatus::ACTIVE,
                'description' => 'Serum pencerah kulit dan memudarkan bekas jerawat dalam 14 hari',
                'og_title' => 'Promo Diskon Somethinc Niacinamide Serum 10%',
                'og_description' => 'Dapatkan diskon eksklusif 35% Somethinc Niacinamide original hanya di Shopee.',
                'og_image_url' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600&auto=format&fit=crop&q=80',
                'price' => 119000,
                'discount_tag' => 'Diskon 35%',
                'category' => 'Skincare',
                'is_in_catalog' => true,
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'TWS Bluetooth Earphone ANC Mini Baseus',
                'original_url' => 'https://shopee.co.id/Baseus-Bowie-E16-TWS-Bluetooth-5.3-Earphone-i.189728392.23849182741',
                'slug' => 'tws-anc-promo',
                'status' => LinkStatus::ACTIVE,
                'description' => 'Audio bass jernih dengan active noise cancelling dan baterai 30 jam',
                'og_title' => 'Baseus TWS ANC Earphone Promo Shopee',
                'og_description' => 'Earphone TWS Bluetooth 5.3 murah dengan peredam kebisingan aktif.',
                'og_image_url' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80',
                'price' => 189000,
                'discount_tag' => 'Flash Sale',
                'category' => 'Gadget',
                'is_in_catalog' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Sunscreen SPF 50+ PA++++ Skintific',
                'original_url' => 'https://shopee.co.id/SKINTIFIC-All-Day-Light-Sunscreen-Mist-SPF-50-PA-50ml-i.51239847.192837465',
                'slug' => 'sunscreen-skintific',
                'status' => LinkStatus::ACTIVE,
                'description' => 'Sunscreen mist praktis no whitecast untuk reapply kapan saja',
                'og_title' => 'Skintific Sunscreen Mist Promo Shopee',
                'og_description' => 'Proteksi sinar UV maksimal tanpa merusak makeup.',
                'og_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=600&auto=format&fit=crop&q=80',
                'price' => 89000,
                'discount_tag' => 'Free Ongkir',
                'category' => 'Skincare',
                'is_in_catalog' => true,
                'is_featured' => false,
                'sort_order' => 3,
            ],
            [
                'title' => 'Mechanical Keyboard RGB 75% Wireless',
                'original_url' => 'https://shopee.co.id/Rexus-Daxa-M71-Pro-Wireless-Mechanical-Keyboard-i.12938472.9182736451',
                'slug' => 'keyboard-mech',
                'status' => LinkStatus::INACTIVE,
                'description' => 'Keyboard mechanical gaming wireless hotswappable',
                'og_title' => 'Keyboard Mechanical Gaming Wireless Promo',
                'og_description' => 'Keyboard hotswappable switch red dengan RGB backlighting.',
                'og_image_url' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600&auto=format&fit=crop&q=80',
                'price' => 450000,
                'discount_tag' => 'Diskon 20%',
                'category' => 'Gadget',
                'is_in_catalog' => false,
                'is_featured' => false,
                'sort_order' => 4,
            ],
        ];

        $createdLinks = [];
        foreach ($sampleLinks as $linkData) {
            $createdLinks[] = Link::updateOrCreate(
                ['slug' => $linkData['slug']],
                array_merge($linkData, ['user_id' => $affiliate->id])
            );
        }

        // 5. Seed Historical Click Analytics & Daily Summaries (30 Days)
        $channels = ['tiktok', 'instagram', 'whatsapp', 'youtube', 'search', 'direct'];
        $browsers = ['chrome', 'safari', 'tiktok', 'instagram'];
        $platforms = ['android', 'ios', 'windows', 'macos'];

        foreach ($createdLinks as $linkIndex => $link) {
            $baseClicksPerDay = match ($linkIndex) {
                0 => 140, // somethinc-serum
                1 => 95,  // tws
                2 => 70,  // sunscreen
                default => 25,
            };

            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dateString = $date->format('Y-m-d');

                // Random variance
                $dayClicks = max(10, intval($baseClicksPerDay + rand(-25, 45)));
                $mobileClicks = intval($dayClicks * 0.78);
                $desktopClicks = intval($dayClicks * 0.18);
                $tabletClicks = $dayClicks - $mobileClicks - $desktopClicks;

                $breakdown = [
                    'tiktok' => intval($dayClicks * 0.42),
                    'instagram' => intval($dayClicks * 0.31),
                    'whatsapp' => intval($dayClicks * 0.14),
                    'youtube' => intval($dayClicks * 0.08),
                    'search' => intval($dayClicks * 0.03),
                    'direct' => $dayClicks - intval($dayClicks * 0.98),
                ];

                LinkClickSummary::updateOrCreate(
                    ['link_id' => $link->id, 'date' => $dateString],
                    [
                        'user_id' => $affiliate->id,
                        'total_clicks' => $dayClicks,
                        'unique_clicks' => intval($dayClicks * 0.88),
                        'mobile_clicks' => $mobileClicks,
                        'desktop_clicks' => $desktopClicks,
                        'tablet_clicks' => $tabletClicks,
                        'top_channel' => 'tiktok',
                        'channel_breakdown' => $breakdown,
                    ]
                );

                // Sample raw click logs for recent days
                if ($i <= 2) {
                    for ($c = 0; $c < min(15, $dayClicks); $c++) {
                        $chan = $channels[array_rand($channels)];
                        $platform = $platforms[array_rand($platforms)];
                        $device = in_array($platform, ['android', 'ios']) ? 'mobile' : 'desktop';

                        LinkClick::create([
                            'link_id' => $link->id,
                            'user_id' => $affiliate->id,
                            'clicked_at' => $date->copy()->addMinutes(rand(10, 1400)),
                            'ip_address' => '114.124.' . rand(10, 250) . '.0',
                            'referrer' => $chan === 'direct' ? null : "https://{$chan}.com/promo",
                            'referrer_channel' => $chan,
                            'device_type' => $device,
                            'browser' => $browsers[array_rand($browsers)],
                            'platform' => $platform,
                            'user_agent' => 'Mozilla/5.0 Sample Agent',
                        ]);
                    }
                }
            }
        }
    }
}
