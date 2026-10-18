<?php

namespace App\Services;

use App\Models\CommissionImport;
use App\Models\Conversion;
use App\Models\Link;
use App\Models\LinkClickSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Commission numbers per user, read from imported conversions and joined with clicks.
 */
class CommissionService
{
    /**
     * Totals for the period. $days = null means all time.
     *
     * @return array{completed: float, pending: float, orders: int, cancelled: int, clicks: int, per_click: ?float, conversion_rate: ?float}
     */
    public function summary(int $userId, ?int $days): array
    {
        $row = $this->inPeriod(Conversion::forUser($userId), $days)
            ->selectRaw("
                COALESCE(SUM(commission) FILTER (WHERE status = 'completed'), 0) AS completed,
                COALESCE(SUM(commission) FILTER (WHERE status = 'pending'), 0) AS pending,
                COUNT(DISTINCT order_id) FILTER (WHERE status <> 'cancelled') AS orders,
                COUNT(DISTINCT order_id) FILTER (WHERE status = 'cancelled') AS cancelled
            ")
            ->first();

        $clicks = (int) $this->clicksQuery($userId, $days)->sum('total_clicks');
        $earned = (float) $row->completed + (float) $row->pending;

        return [
            'completed' => (float) $row->completed,
            'pending' => (float) $row->pending,
            'orders' => (int) $row->orders,
            'cancelled' => (int) $row->cancelled,
            'clicks' => $clicks,
            'per_click' => $clicks > 0 ? round($earned / $clicks, 2) : null,
            'conversion_rate' => $clicks > 0 ? round($row->orders / $clicks * 100, 2) : null,
        ];
    }

    /**
     * Matched links ranked by commission, with clicks over the same period.
     */
    public function perLink(int $userId, ?int $days): Collection
    {
        $rows = $this->inPeriod(Conversion::forUser($userId)->notCancelled()->whereNotNull('link_id'), $days)
            ->groupBy('link_id')
            ->selectRaw('link_id, COUNT(DISTINCT order_id) AS orders, SUM(commission) AS commission, SUM(order_value) AS order_value')
            ->orderByDesc('commission')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $links = Link::whereIn('id', $rows->pluck('link_id'))->get(['id', 'title', 'slug', 'original_url'])->keyBy('id');
        $clicks = $this->clicksQuery($userId, $days)
            ->whereIn('link_id', $rows->pluck('link_id'))
            ->groupBy('link_id')
            ->selectRaw('link_id, SUM(total_clicks) AS clicks')
            ->pluck('clicks', 'link_id');

        return $rows->map(function ($row) use ($links, $clicks) {
            $linkClicks = (int) ($clicks[$row->link_id] ?? 0);

            return [
                'link' => $links[$row->link_id] ?? null,
                'orders' => (int) $row->orders,
                'commission' => (float) $row->commission,
                'order_value' => (float) $row->order_value,
                'clicks' => $linkClicks,
                'per_click' => $linkClicks > 0 ? round($row->commission / $linkClicks, 2) : null,
            ];
        })->filter(fn ($row) => $row['link'] !== null)->values();
    }

    /**
     * Orders that couldn't be attributed, grouped by product so they can be assigned in one go.
     */
    public function unmatched(int $userId, int $limit = 20): Collection
    {
        return Conversion::forUser($userId)
            ->notCancelled()
            ->whereNull('link_id')
            ->groupBy('item_key_group')
            ->selectRaw("
                CASE WHEN item_ref IS NOT NULL THEN 'i:' || item_ref ELSE 'n:' || COALESCE(product_name, '') END AS item_key_group,
                MAX(product_name) AS product_name,
                MAX(item_ref) AS item_ref,
                MAX(sub_id) AS sub_id,
                COUNT(DISTINCT order_id) AS orders,
                SUM(commission) AS commission
            ")
            ->orderByDesc('commission')
            ->limit($limit)
            ->get();
    }

    public function unmatchedCount(int $userId): int
    {
        return Conversion::forUser($userId)->notCancelled()->whereNull('link_id')->count();
    }

    /**
     * Assign every unmatched order of a product group to a link. The link also learns
     * the item ID, so future imports of that product match on their own.
     *
     * @return int Orders assigned.
     */
    public function assignGroup(int $userId, string $groupKey, Link $link): int
    {
        $query = Conversion::forUser($userId)->whereNull('link_id');

        if (str_starts_with($groupKey, 'i:')) {
            $itemRef = substr($groupKey, 2);
            $query->where('item_ref', $itemRef);

            if (! $link->product_ref) {
                $link->update(['product_ref' => $itemRef]);
            }
        } else {
            $productName = substr($groupKey, 2);
            $query->whereNull('item_ref')->where(fn ($q) => $productName === ''
                ? $q->whereNull('product_name')->orWhere('product_name', '')
                : $q->where('product_name', $productName));
        }

        return $query->update(['link_id' => $link->id]);
    }

    public function recentImports(int $userId, int $limit = 5): Collection
    {
        return CommissionImport::where('user_id', $userId)->latest()->limit($limit)->get();
    }

    public function hasConversions(int $userId): bool
    {
        return Conversion::forUser($userId)->exists();
    }

    protected function inPeriod($query, ?int $days)
    {
        return $days === null
            ? $query
            : $query->where(DB::raw('COALESCE(ordered_at, created_at)'), '>=', now()->subDays($days - 1)->startOfDay());
    }

    protected function clicksQuery(int $userId, ?int $days)
    {
        $query = LinkClickSummary::where('user_id', $userId);

        return $days === null ? $query : $query->where('date', '>=', now()->subDays($days - 1)->toDateString());
    }
}
