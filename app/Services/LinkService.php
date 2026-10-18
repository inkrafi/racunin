<?php

namespace App\Services;

use App\Actions\Links\CreateLinkAction;
use App\Actions\Links\DeleteLinkAction;
use App\Actions\Links\UpdateLinkAction;
use App\DTOs\LinkData;
use App\Enums\LinkStatus;
use App\Models\Link;
use App\Models\LinkClickSummary;
use App\Repositories\Contracts\LinkRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class LinkService extends BaseService
{
    protected const REDIRECT_CACHE_TTL = 3600;
    protected const MISSING_SLUG_CACHE_TTL = 300;

    public function __construct(
        protected LinkRepositoryInterface $linkRepository,
        protected CreateLinkAction $createLinkAction,
        protected UpdateLinkAction $updateLinkAction,
        protected DeleteLinkAction $deleteLinkAction,
    ) {}

    public function createLink(LinkData $data): Link
    {
        return $this->createLinkAction->execute($data);
    }

    public function updateLink(Link $link, LinkData $data): Link
    {
        return $this->updateLinkAction->execute($link, $data);
    }

    public function deleteLink(Link $link): bool
    {
        return $this->deleteLinkAction->execute($link);
    }

    public function toggleStatus(Link $link): Link
    {
        $newStatus = $link->status === LinkStatus::ACTIVE ? LinkStatus::INACTIVE : LinkStatus::ACTIVE;

        // Redirect cache is invalidated by the Link model's saved event
        $this->linkRepository->update($link->id, [
            'status' => $newStatus->value,
        ]);

        return $link->fresh();
    }

    /**
     * Resolve a short-link slug for the redirect hot path.
     *
     * Returns a small cached array (not a serialized model) with the fields the
     * redirect and social preview need, or null when the slug does not exist.
     * Unknown slugs are negatively cached briefly so scanners can't hammer the DB.
     *
     * @return array{id: int, user_id: int, original_url: string, status: string, title: string, og_title: ?string, og_description: ?string, og_image_url: ?string, description: ?string}|null
     */
    public function resolveSlug(string $slug): ?array
    {
        $cacheKey = Link::redirectCacheKey($slug);

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return ($cached['missing'] ?? false) ? null : $cached;
            }
        } catch (\Throwable $e) {
            // Cache unavailable: fall through to the database
        }

        $link = $this->linkRepository->findBySlug($slug);

        $payload = $link ? [
            'id' => $link->id,
            'user_id' => $link->user_id,
            'original_url' => $link->original_url,
            'status' => $link->status->value,
            'title' => $link->title,
            'og_title' => $link->og_title,
            'og_description' => $link->og_description,
            'og_image_url' => $link->og_image_url,
            'description' => $link->description,
        ] : ['missing' => true];

        try {
            Cache::put($cacheKey, $payload, $link ? self::REDIRECT_CACHE_TTL : self::MISSING_SLUG_CACHE_TTL);
        } catch (\Throwable $e) {
            // Silently ignore cache write failures
        }

        return $link ? $payload : null;
    }

    /**
     * Daily click counts for the given links over the last N days (one summary-table query),
     * used for the per-row sparkline in the links list.
     *
     * @param  list<int>  $linkIds
     * @return array<int, array{total: int, daily: list<int>}>
     */
    public function getRecentClicks(array $linkIds, int $days = 7): array
    {
        if (empty($linkIds)) {
            return [];
        }

        $start = now()->subDays($days - 1)->startOfDay();

        $rows = LinkClickSummary::query()
            ->whereIn('link_id', $linkIds)
            ->where('date', '>=', $start->format('Y-m-d'))
            ->get(['link_id', 'date', 'total_clicks']);

        $series = [];
        foreach ($linkIds as $id) {
            $series[$id] = ['total' => 0, 'daily' => array_fill(0, $days, 0)];
        }

        foreach ($rows as $row) {
            $index = (int) $start->diffInDays($row->date->startOfDay());
            if ($index >= 0 && $index < $days) {
                $series[$row->link_id]['daily'][$index] += $row->total_clicks;
                $series[$row->link_id]['total'] += $row->total_clicks;
            }
        }

        return $series;
    }

    public function getAffiliateLinks(int $userId, ?string $search = null, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->linkRepository->getForUser($userId, $search, $status, $perPage);
    }

    public function getAllLinksForAdmin(?string $search = null, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->linkRepository->getAllForAdmin($search, $status, $perPage);
    }

    public function getStats(int $userId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            $total = $this->linkRepository->countAll();
            $active = $this->linkRepository->countAll(LinkStatus::ACTIVE->value);
            $inactive = $this->linkRepository->countAll(LinkStatus::INACTIVE->value);
        } else {
            $total = $this->linkRepository->countForUser($userId);
            $active = $this->linkRepository->countForUser($userId, LinkStatus::ACTIVE->value);
            $inactive = $this->linkRepository->countForUser($userId, LinkStatus::INACTIVE->value);
        }

        return [
            'total_links' => $total,
            'active_links' => $active,
            'inactive_links' => $inactive,
        ];
    }
}
