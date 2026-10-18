<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface AnalyticsRepositoryInterface
{
    /**
     * @return array{total_clicks: int, unique_visitors: int, mobile_clicks: int, desktop_clicks: int, tablet_clicks: int}
     */
    public function getSummaryMetrics(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): array;

    public function getDailyTrends(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): Collection;

    /**
     * @return list<array{channel: string, label: string, count: int, percentage: float|int}> Sorted by count, descending.
     */
    public function getChannelBreakdown(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): array;

    public function getTopPerformingLinks(int $userId, int $limit = 10, int $days = 30, bool $isAdmin = false): Collection;
}
