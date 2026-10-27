<?php

namespace App\Actions\Analytics;

use App\DTOs\ClickEventData;
use Illuminate\Support\Facades\DB;

class RecordLandingPageViewAction
{
    public function __construct(
        protected ParseUserAgentAction $parseUserAgentAction,
    ) {}

    /**
     * Count one visit to a link's landing page (bots and link previews are skipped).
     */
    public function execute(int $linkId, ClickEventData $event): void
    {
        if ($this->parseUserAgentAction->execute($event->userAgent)['device_type'] === 'bot') {
            return;
        }

        $date = $event->clickedAt->format('Y-m-d');
        // Same daily-rotating hash as clicks: unique visitors without a stable identifier
        $visitorHash = empty($event->ipAddress) && empty($event->userAgent)
            ? null
            : hash_hmac('sha256', $date . '|' . $event->ipAddress . '|' . $event->userAgent, (string) config('app.key'));

        DB::transaction(function () use ($linkId, $date, $visitorHash) {
            $isUnique = $visitorHash === null || DB::table('landing_page_visitors')->insertOrIgnore([
                'link_id' => $linkId,
                'date' => $date,
                'visitor_hash' => $visitorHash,
            ]) === 1;

            $now = now();

            // Atomic upsert (safe with concurrent queue workers)
            DB::statement(
                <<<'SQL'
                INSERT INTO landing_page_views (link_id, date, views, unique_views, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?, ?)
                ON CONFLICT (link_id, date) DO UPDATE SET
                    views = landing_page_views.views + 1,
                    unique_views = landing_page_views.unique_views + EXCLUDED.unique_views,
                    updated_at = EXCLUDED.updated_at
                SQL,
                [$linkId, $date, (int) $isUnique, $now, $now]
            );
        });
    }
}
