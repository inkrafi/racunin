<?php

namespace App\Repositories\Contracts;

use App\Models\Link;
use Illuminate\Pagination\LengthAwarePaginator;

interface LinkRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySlug(string $slug): ?Link;

    public function slugExists(string $slug, ?int $excludeId = null): bool;

    public function getForUser(int $userId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator;

    public function getAllForAdmin(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator;

    public function countForUser(int $userId, ?string $status = null): int;

    public function countAll(?string $status = null): int;
}
