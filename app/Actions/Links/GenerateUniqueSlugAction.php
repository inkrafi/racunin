<?php

namespace App\Actions\Links;

use App\Repositories\Contracts\LinkRepositoryInterface;
use Illuminate\Support\Str;

class GenerateUniqueSlugAction
{
    protected const RESERVED_SLUGS = [
        'login', 'register', 'logout', 'dashboard', 'admin', 'api', 'up', 'build',
        'storage', 'catalog', 'bio', 'settings', 'analytics', 'links', 'user', 'profile'
    ];

    public function __construct(
        protected LinkRepositoryInterface $linkRepository
    ) {}

    public function execute(?string $customSlug = null, int $length = 6): string
    {
        if ($customSlug) {
            $slug = Str::slug($customSlug);

            if (in_array(strtolower($slug), self::RESERVED_SLUGS, true)) {
                throw new \InvalidArgumentException("'{$slug}' dipakai oleh sistem. Pilih slug lain.");
            }

            if ($this->linkRepository->slugExists($slug)) {
                throw new \InvalidArgumentException("Slug '{$slug}' sudah dipakai link lain.");
            }

            return $slug;
        }

        do {
            // Generate a clean random alphanumeric slug
            $slug = Str::lower(Str::random($length));
        } while (
            in_array($slug, self::RESERVED_SLUGS, true) ||
            $this->linkRepository->slugExists($slug)
        );

        return $slug;
    }
}
