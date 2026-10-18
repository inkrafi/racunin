<?php

namespace App\Actions\Analytics;

use App\DTOs\ClickEventData;
use App\Models\LinkClick;
use Illuminate\Support\Facades\DB;

class RecordClickAction
{
    /** In-app browsers that rarely send a Referer header; their UA identifies the channel instead. */
    protected const IN_APP_BROWSER_CHANNELS = ['tiktok', 'instagram', 'facebook', 'threads'];

    public function __construct(
        protected ParseUserAgentAction $parseUserAgentAction,
        protected ResolveReferrerChannelAction $resolveReferrerChannelAction,
    ) {}

    public function execute(int $linkId, int $userId, ClickEventData $event): LinkClick
    {
        $uaInfo = $this->parseUserAgentAction->execute($event->userAgent);
        $channel = $this->resolveReferrerChannelAction->execute($event->referrer);

        if ($channel === 'direct' && in_array($uaInfo['browser'], self::IN_APP_BROWSER_CHANNELS, true)) {
            $channel = $uaInfo['browser'];
        }

        $date = $event->clickedAt->format('Y-m-d');
        $visitorHash = $this->visitorHash($event, $date);
        $isBot = $uaInfo['device_type'] === 'bot';

        return DB::transaction(function () use ($linkId, $userId, $event, $uaInfo, $channel, $date, $visitorHash, $isBot) {
            // 1. Record raw click (bots are kept for auditing but never aggregated)
            $click = LinkClick::create([
                'link_id' => $linkId,
                'user_id' => $userId,
                'clicked_at' => $event->clickedAt,
                'ip_address' => $this->anonymizeIp($event->ipAddress),
                'visitor_hash' => $visitorHash,
                'referrer' => $event->referrer,
                'referrer_channel' => $channel,
                'device_type' => $uaInfo['device_type'],
                'browser' => $uaInfo['browser'],
                'platform' => $uaInfo['platform'],
                'user_agent' => $event->userAgent,
            ]);

            if ($isBot) {
                return $click;
            }

            // 2. First visit of this visitor to this link today?
            $isUnique = $visitorHash === null || DB::table('link_unique_visitors')->insertOrIgnore([
                'link_id' => $linkId,
                'date' => $date,
                'visitor_hash' => $visitorHash,
            ]) === 1;

            // 3. Atomic upsert into the daily summary (safe with concurrent queue workers)
            $this->incrementDailySummary($linkId, $userId, $date, $uaInfo['device_type'], $channel, $isUnique);

            return $click;
        });
    }

    protected function incrementDailySummary(int $linkId, int $userId, string $date, string $deviceType, string $channel, bool $isUnique): void
    {
        $now = now();

        $summaryId = DB::selectOne(
            <<<'SQL'
            INSERT INTO link_click_summaries
                (link_id, user_id, date, total_clicks, unique_clicks, mobile_clicks, desktop_clicks, tablet_clicks,
                 channel_breakdown, top_channel, created_at, updated_at)
            VALUES (?, ?, ?, 1, ?, ?, ?, ?, jsonb_build_object(?::text, 1), ?, ?, ?)
            ON CONFLICT (link_id, date) DO UPDATE SET
                total_clicks = link_click_summaries.total_clicks + 1,
                unique_clicks = link_click_summaries.unique_clicks + EXCLUDED.unique_clicks,
                mobile_clicks = link_click_summaries.mobile_clicks + EXCLUDED.mobile_clicks,
                desktop_clicks = link_click_summaries.desktop_clicks + EXCLUDED.desktop_clicks,
                tablet_clicks = link_click_summaries.tablet_clicks + EXCLUDED.tablet_clicks,
                channel_breakdown = COALESCE(link_click_summaries.channel_breakdown, '{}'::jsonb)
                    || jsonb_build_object(?::text, COALESCE((link_click_summaries.channel_breakdown ->> ?)::int, 0) + 1),
                updated_at = EXCLUDED.updated_at
            RETURNING id
            SQL,
            [
                $linkId, $userId, $date,
                (int) $isUnique,
                (int) ($deviceType === 'mobile'),
                (int) ($deviceType === 'desktop'),
                (int) ($deviceType === 'tablet'),
                $channel, $channel, $now, $now,
                $channel, $channel,
            ]
        )->id;

        // The row is locked by the upsert above, so the dominant channel is computed consistently
        DB::update(
            <<<'SQL'
            UPDATE link_click_summaries
            SET top_channel = (
                SELECT key FROM jsonb_each_text(channel_breakdown)
                ORDER BY value::int DESC, key
                LIMIT 1
            )
            WHERE id = ?
            SQL,
            [$summaryId]
        );
    }

    /**
     * Salted hash of IP + user agent that rotates daily, so unique visitors can
     * be counted without storing a stable identifier.
     */
    protected function visitorHash(ClickEventData $event, string $date): ?string
    {
        if (empty($event->ipAddress) && empty($event->userAgent)) {
            return null;
        }

        return hash_hmac('sha256', $date . '|' . $event->ipAddress . '|' . $event->userAgent, (string) config('app.key'));
    }

    protected function anonymizeIp(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::';
        }

        return $ip;
    }
}
