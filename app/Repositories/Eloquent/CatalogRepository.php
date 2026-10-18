<?php

namespace App\Repositories\Eloquent;

use App\Models\Catalog;
use App\Models\Link;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CatalogRepository extends BaseRepository implements CatalogRepositoryInterface
{
    public function __construct(Catalog $model)
    {
        parent::__construct($model);
    }

    public function findByUsername(string $username): ?Catalog
    {
        return $this->model->newQuery()
            ->with('user:id,name,email')
            ->where('username', strtolower($username))
            ->where('is_active', true)
            ->first();
    }

    public function findByUserId(int $userId): ?Catalog
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->first();
    }

    public function usernameExists(string $username, ?int $excludeCatalogId = null): bool
    {
        $query = $this->model->newQuery()
            ->where('username', strtolower($username));

        if ($excludeCatalogId !== null) {
            $query->where('id', '!=', $excludeCatalogId);
        }

        return $query->exists();
    }

    public function createOrUpdateForUser(int $userId, array $attributes): Catalog
    {
        return $this->model->newQuery()->updateOrCreate(
            ['user_id' => $userId],
            $attributes
        );
    }

    public function getCatalogLinks(int $userId, ?string $search = null, ?string $category = null, ?array $searchEmbedding = null, ?int $limit = null): Collection
    {
        $query = Link::query()
            ->where('user_id', $userId)
            ->inCatalog()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest();

        if ($search) {
            if (! empty($searchEmbedding)) {
                $embeddingStr = json_encode($searchEmbedding);
                // Filter relevant products and sort by cosine distance
                $query->whereRaw('embedding <=> ? < 0.65', [$embeddingStr])
                      ->reorder() // Remove default sorting
                      ->orderByRaw('embedding <=> ?', [$embeddingStr]);
            } else {
                // Keyword search fallback
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', "%{$search}%")
                        ->orWhere('og_title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('category', 'ilike', "%{$search}%");
                });
            }
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function countCatalogLinks(int $userId): int
    {
        return Link::query()
            ->where('user_id', $userId)
            ->inCatalog()
            ->count();
    }

    public function getCatalogCategories(int $userId): array
    {
        return Link::query()
            ->where('user_id', $userId)
            ->inCatalog()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->toArray();
    }
}
