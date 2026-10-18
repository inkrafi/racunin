<?php

namespace App\Actions\Analytics;

class ParseUserAgentAction
{
    public function execute(?string $userAgent): array
    {
        if (empty($userAgent)) {
            return [
                'device_type' => 'unknown',
                'platform' => 'unknown',
                'browser' => 'unknown',
            ];
        }

        $ua = strtolower($userAgent);

        // 1. Detect Bots
        if (preg_match('/(bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegrambot|twitterbot)/i', $ua)) {
            return [
                'device_type' => 'bot',
                'platform' => 'bot',
                'browser' => 'bot',
            ];
        }

        // 2. Detect Device Type
        $deviceType = 'desktop';
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|iemobile|wpdesktop)/i', $ua)) {
            $deviceType = 'mobile';
        }

        // 3. Detect Platform / OS
        $platform = 'unknown';
        if (str_contains($ua, 'android')) {
            $platform = 'android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            $platform = 'ios';
        } elseif (str_contains($ua, 'windows') || str_contains($ua, 'win32') || str_contains($ua, 'win64')) {
            $platform = 'windows';
        } elseif (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os x')) {
            $platform = 'macos';
        } elseif (str_contains($ua, 'linux')) {
            $platform = 'linux';
        }

        // 4. Detect In-App & Standard Browsers (Threads identifies itself as "Barcelona")
        $browser = 'other';
        if (preg_match('/tiktok|musical_ly|bytedancewebview|trill_/', $ua)) {
            $browser = 'tiktok';
        } elseif (str_contains($ua, 'barcelona')) {
            $browser = 'threads';
        } elseif (str_contains($ua, 'instagram')) {
            $browser = 'instagram';
        } elseif (str_contains($ua, 'fbav') || str_contains($ua, 'fban') || str_contains($ua, 'facebook')) {
            $browser = 'facebook';
        } elseif (preg_match('#\bline/\d#', $ua)) {
            $browser = 'line';
        } elseif ($platform === 'android' && str_contains($ua, '; wv)')) {
            // Unbranded Android WebView: Telegram, X and most other apps' in-app browsers
            $browser = 'webview';
        } elseif (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) {
            $browser = 'edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            $browser = 'opera';
        } elseif (str_contains($ua, 'chrome') || str_contains($ua, 'crios')) {
            $browser = 'chrome';
        } elseif (str_contains($ua, 'safari')) {
            $browser = 'safari';
        } elseif (str_contains($ua, 'firefox') || str_contains($ua, 'fxios')) {
            $browser = 'firefox';
        }

        return [
            'device_type' => $deviceType,
            'platform' => $platform,
            'browser' => $browser,
        ];
    }
}
