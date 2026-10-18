<?php

namespace App\Actions\Commissions;

use App\Models\Conversion;
use App\Models\Link;
use App\Support\ProductRef;

/**
 * Attributes unmatched orders to the user's links: first by Sub ID (or a slug
 * typed as Sub ID), then by marketplace item ID when exactly one link sells it.
 */
class MatchConversionsAction
{
    /**
     * @return int Number of orders newly matched.
     */
    public function execute(int $userId): int
    {
        $links = Link::forUser($userId)->get(['id', 'slug', 'sub_id', 'product_ref']);

        $bySubId = [];
        foreach ($links as $link) {
            // Sub IDs often can't contain "-", so "serum-glowing" may come back as "serumglowing"
            foreach ([$link->slug, str_replace('-', '', $link->slug)] as $alias) {
                $bySubId[ProductRef::normalizeSubId($alias)] ??= $link->id;
            }
        }
        foreach ($links as $link) {
            // An explicit Sub ID beats a slug alias
            if ($link->sub_id) {
                $bySubId[$link->sub_id] = $link->id;
            }
        }

        $byProduct = $links->whereNotNull('product_ref')->groupBy('product_ref')
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => $group->first()->id)
            ->all();

        $assignments = [];
        Conversion::forUser($userId)
            ->whereNull('link_id')
            ->select(['id', 'sub_id', 'item_ref'])
            ->chunkById(1000, function ($conversions) use ($bySubId, $byProduct, &$assignments) {
                foreach ($conversions as $conversion) {
                    $linkId = ($conversion->sub_id ? ($bySubId[$conversion->sub_id] ?? null) : null)
                        ?? ($conversion->item_ref ? ($byProduct[$conversion->item_ref] ?? null) : null);

                    if ($linkId) {
                        $assignments[$linkId][] = $conversion->id;
                    }
                }
            });

        $matched = 0;
        foreach ($assignments as $linkId => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $matched += Conversion::whereIn('id', $chunk)->update(['link_id' => $linkId]);
            }
        }

        return $matched;
    }
}
