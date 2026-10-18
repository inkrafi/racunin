<?php

namespace App\Actions\Links;

use App\Models\Link;
use App\Repositories\Contracts\LinkRepositoryInterface;

class DeleteLinkAction
{
    public function __construct(
        protected LinkRepositoryInterface $linkRepository
    ) {}

    public function execute(Link $link): bool
    {
        // Redirect cache is invalidated by the Link model's deleted event
        return $this->linkRepository->delete($link->id);
    }
}
