<?php

namespace App\Actions\Links;

use App\DTOs\LinkData;
use App\Models\Link;
use App\Repositories\Contracts\LinkRepositoryInterface;

class CreateLinkAction
{
    public function __construct(
        protected LinkRepositoryInterface $linkRepository,
        protected GenerateUniqueSlugAction $generateUniqueSlugAction
    ) {}

    public function execute(LinkData $data): Link
    {
        $slug = $this->generateUniqueSlugAction->execute($data->slug);

        $payload = $data->toArray();
        $payload['slug'] = $slug;

        return $this->linkRepository->create($payload);
    }
}
