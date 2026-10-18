<?php

namespace App\Repositories\Contracts;

use App\Models\Catalog;
use Illuminate\Database\Eloquent\Collection;

interface CatalogRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUsername(string $username): ?Catalog;

    public function findByUserId(int $userId): ?Catalog;

    public function usernameExists(string $username, ?int $excludeCatalogId = null): bool;

    public function createOrUpdateForUser(int $userId, array $attributes): Catalog;

    /**
     * @param  array<float>|null  $searchEmbedding  When given, rank by vector similarity; otherwise fall back to keyword search.
     */
    public function getCatalogLinks(int $userId, ?string $search = null, ?string $category = null, ?array $searchEmbedding = null, ?int $limit = null): Collection;

    public function countCatalogLinks(int $userId): int;

    public function getCatalogCategories(int $userId): array;
}
