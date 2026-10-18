<?php

namespace App\Services;

use App\Models\Link;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AnalyticsService extends BaseService
{
    public function __construct(
        protected AnalyticsRepositoryInterface $analyticsRepository
    ) {}

    /**
     * Dashboard analytics in three summary-table queries (metrics, trends, channels).
     */
    public function getDashboardAnalytics(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): array
    {
        $metrics = $this->analyticsRepository->getSummaryMetrics($userId, $linkId, $days, $isAdmin);
        $trends = $this->analyticsRepository->getDailyTrends($userId, $linkId, $days, $isAdmin);
        $channels = $this->analyticsRepository->getChannelBreakdown($userId, $linkId, $days, $isAdmin);

        $metrics['top_channel'] = $channels[0]['channel'] ?? 'direct';

        return [
            'metrics' => $metrics,
            'trends' => $trends,
            // Max clicks for SVG bar/line chart normalization
            'max_daily_clicks' => $trends->max('clicks') ?: 1,
            'channels' => $channels,
            'devices' => $this->deviceBreakdown($metrics),
        ];
    }

    public function getClickTotal(int $userId, int $days = 7, bool $isAdmin = false): int
    {
        return $this->analyticsRepository->getSummaryMetrics($userId, null, $days, $isAdmin)['total_clicks'];
    }

    /**
     * Collect the analytics snapshot the AI insight prompt needs.
     *
     * @return array{metrics: array, channels: array, devices: array, top_links: array}
     */
    public function getInsightInput(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): array
    {
        $analytics = $this->getDashboardAnalytics($userId, $linkId, $days, $isAdmin);

        $topLinks = $this->getTopLinks($userId, 5, $days, $isAdmin)
            ->map(fn ($link) => [
                'title' => $link->title,
                'clicks' => (int) ($link->period_clicks ?? 0),
                'category' => $link->category,
            ])
            ->toArray();

        return [
            'metrics' => $analytics['metrics'],
            'channels' => $analytics['channels'],
            'devices' => $analytics['devices'],
            'top_links' => $topLinks,
        ];
    }

    public function getTopLinks(int $userId, int $limit = 10, int $days = 30, bool $isAdmin = false): Collection
    {
        return $this->analyticsRepository->getTopPerformingLinks($userId, $limit, $days, $isAdmin);
    }

    public function getUserLinksForFilter(int $userId, bool $isAdmin = false): EloquentCollection
    {
        $query = Link::query()->select('id', 'title', 'slug');

        if (! $isAdmin) {
            $query->where('user_id', $userId);
        }

        return $query->latest()->get();
    }

    /**
     * @return array<string, array{count: int, percentage: float|int}>
     */
    protected function deviceBreakdown(array $metrics): array
    {
        $total = $metrics['total_clicks'];
        $breakdown = [];

        foreach (['mobile', 'desktop', 'tablet'] as $device) {
            $count = $metrics["{$device}_clicks"];
            $breakdown[$device] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return $breakdown;
    }
}
