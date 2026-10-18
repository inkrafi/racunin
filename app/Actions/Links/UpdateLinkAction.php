<?php

namespace App\Actions\Links;

use App\DTOs\LinkData;
use App\Models\Link;
use App\Repositories\Contracts\LinkRepositoryInterface;
use Illuminate\Support\Str;

class UpdateLinkAction
{
    public function __construct(
        protected LinkRepositoryInterface $linkRepository
    ) {}

    public function execute(Link $link, LinkData $data): Link
    {
        $payload = $data->toArray();

        // Ownership never changes on update (e.g. when an admin edits an affiliate's link)
        unset($payload['user_id']);

        // If slug changed, validate uniqueness
        if ($data->slug && $data->slug !== $link->slug) {
            $newSlug = Str::slug($data->slug);

            if ($this->linkRepository->slugExists($newSlug, $link->id)) {
                throw new \InvalidArgumentException("Slug '{$newSlug}' sudah dipakai link lain.");
            }

            $payload['slug'] = $newSlug;
        } else {
            $payload['slug'] = $link->slug;
        }

        // Redirect cache (old and new slug) is invalidated by the Link model's saved event
        $this->linkRepository->update($link->id, $payload);

        return $link->fresh();
    }
}
