<?php

namespace App\Services;

use App\Actions\Catalog\SaveCatalogProfileAction;
use App\DTOs\CatalogProfileData;
use App\Models\Catalog;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Support\VectorSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class CatalogService extends BaseService
{
    /** Max new (uncached) query embeddings per visitor per minute on the public catalog. */
    protected const SEMANTIC_SEARCH_PER_MINUTE = 20;

    public function __construct(
        protected CatalogRepositoryInterface $catalogRepository,
        protected SaveCatalogProfileAction $saveCatalogProfileAction,
        protected GeminiService $gemini,
    ) {}

    public function getPublicCatalog(string $username): ?Catalog
    {
        return $this->catalogRepository->findByUsername($username);
    }

    /**
     * @param  string|null  $clientKey  Identifies the visitor (e.g. IP) for rate limiting paid embedding calls.
     */
    public function getCatalogLinks(int $userId, ?string $search = null, ?string $category = null, ?string $clientKey = null, ?int $limit = null): Collection
    {
        $search = $search !== null ? trim($search) : null;
        $embedding = $search ? $this->resolveSearchEmbedding($search, $clientKey) : null;

        return $this->catalogRepository->getCatalogLinks($userId, $search, $category, $embedding, $limit);
    }

    public function countCatalogLinks(int $userId): int
    {
        return $this->catalogRepository->countCatalogLinks($userId);
    }

    /**
     * Get the query embedding from cache, or from Gemini within the visitor's rate limit.
     * Returns null to fall back to keyword search.
     */
    protected function resolveSearchEmbedding(string $search, ?string $clientKey): ?array
    {
        $normalized = mb_strtolower(preg_replace('/\s+/', ' ', $search));

        if (mb_strlen($normalized) < 3 || ! $this->gemini->isConfigured() || ! VectorSearch::isAvailable()) {
            return null;
        }

        $cacheKey = 'catalog:query-embedding:' . sha1(config('gemini.embedding_model') . '|' . $normalized);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rateLimitKey = 'catalog-semantic:' . ($clientKey ?? 'anonymous');
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::SEMANTIC_SEARCH_PER_MINUTE)) {
            return null;
        }
        RateLimiter::hit($rateLimitKey, 60);

        try {
            $embedding = $this->gemini->generateEmbedding($normalized, GeminiService::TASK_QUERY);
        } catch (\Throwable $e) {
            Log::warning('Semantic search fallback to keyword search: ' . $e->getMessage());

            return null;
        }

        if (empty($embedding)) {
            return null;
        }

        Cache::put($cacheKey, $embedding, now()->addDay());

        return $embedding;
    }

    public function getCategories(int $userId): array
    {
        return $this->catalogRepository->getCatalogCategories($userId);
    }

    public function getProfileForUser(int $userId): ?Catalog
    {
        return $this->catalogRepository->findByUserId($userId);
    }

    public function saveProfile(CatalogProfileData $data): Catalog
    {
        return $this->saveCatalogProfileAction->execute($data);
    }

    public function toggleCatalogStatus(int $userId): Catalog
    {
        $profile = $this->catalogRepository->findByUserId($userId);

        if (! $profile) {
            throw new \RuntimeException("Profil katalog belum dibuat.");
        }

        $this->catalogRepository->update($profile->id, [
            'is_active' => ! $profile->is_active,
        ]);

        return $profile->fresh();
    }
}
