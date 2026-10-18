<?php

namespace App\Repositories\Eloquent;

use App\Models\Link;
use App\Models\LinkClickSummary;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All dashboard reads go through the pre-aggregated link_click_summaries table,
 * so dashboard cost is independent of raw click volume.
 */
class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function getSummaryMetrics(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): array
    {
        $totals = $this->summaryQuery($userId, $linkId, $days, $isAdmin)
            ->selectRaw('
                COALESCE(SUM(total_clicks), 0) as total_clicks,
                COALESCE(SUM(unique_clicks), 0) as unique_visitors,
                COALESCE(SUM(mobile_clicks), 0) as mobile_clicks,
                COALESCE(SUM(desktop_clicks), 0) as desktop_clicks,
                COALESCE(SUM(tablet_clicks), 0) as tablet_clicks
            ')
            ->toBase()
            ->first();

        return [
            'total_clicks' => (int) ($totals->total_clicks ?? 0),
            'unique_visitors' => (int) ($totals->unique_visitors ?? 0),
            'mobile_clicks' => (int) ($totals->mobile_clicks ?? 0),
            'desktop_clicks' => (int) ($totals->desktop_clicks ?? 0),
            'tablet_clicks' => (int) ($totals->tablet_clicks ?? 0),
        ];
    }

    public function getDailyTrends(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): Collection
    {
        $records = $this->summaryQuery($userId, $linkId, $days, $isAdmin)
            ->groupBy('date')
            ->select('date', DB::raw('SUM(total_clicks) as total_clicks'))
            ->toBase()
            ->pluck('total_clicks', 'date')
            ->mapWithKeys(fn ($count, $date) => [Carbon::parse($date)->format('Y-m-d') => (int) $count]);

        // Build continuous date series with zeros for missing days
        $trend = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);

            $trend->push([
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('d M'),
                'clicks' => $records[$day->format('Y-m-d')] ?? 0,
            ]);
        }

        return $trend;
    }

    public function getChannelBreakdown(int $userId, ?int $linkId = null, int $days = 30, bool $isAdmin = false): array
    {
        $results = $this->summaryQuery($userId, $linkId, $days, $isAdmin)
            ->whereNotNull('channel_breakdown')
            ->crossJoin(DB::raw('jsonb_each_text(link_click_summaries.channel_breakdown) as cb(channel, clicks)'))
            ->groupBy('cb.channel')
            ->selectRaw('cb.channel as channel, SUM(cb.clicks::int) as count')
            ->orderByDesc('count')
            ->orderBy('channel')
            ->toBase()
            ->get();

        $total = $results->sum('count');

        return $results->map(fn ($item) => [
            'channel' => $item->channel,
            'label' => ucfirst($item->channel),
            'count' => (int) $item->count,
            'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
        ])->values()->toArray();
    }

    public function getTopPerformingLinks(int $userId, int $limit = 10, int $days = 30, bool $isAdmin = false): Collection
    {
        [$startDate, $endDate] = $this->dateRange($days);

        $query = Link::query()
            ->with('user:id,name,email')
            ->leftJoin('link_click_summaries', function ($join) use ($startDate, $endDate) {
                $join->on('links.id', '=', 'link_click_summaries.link_id')
                    ->whereBetween('link_click_summaries.date', [$startDate, $endDate]);
            });

        if (! $isAdmin) {
            $query->where('links.user_id', $userId);
        }

        return $query->groupBy('links.id')
            ->select(
                'links.*',
                DB::raw('COALESCE(SUM(link_click_summaries.total_clicks), 0) as period_clicks'),
                DB::raw('COALESCE(SUM(link_click_summaries.unique_clicks), 0) as unique_clicks')
            )
            ->orderByDesc('period_clicks')
            ->limit($limit)
            ->get();
    }

    protected function summaryQuery(int $userId, ?int $linkId, int $days, bool $isAdmin): Builder
    {
        $query = LinkClickSummary::query()
            ->whereBetween('link_click_summaries.date', $this->dateRange($days));

        if (! $isAdmin) {
            $query->where('link_click_summaries.user_id', $userId);
        }

        if ($linkId) {
            $query->where('link_click_summaries.link_id', $linkId);
        }

        return $query;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function dateRange(int $days): array
    {
        return [
            Carbon::now()->subDays($days - 1)->format('Y-m-d'),
            Carbon::now()->format('Y-m-d'),
        ];
    }
}
