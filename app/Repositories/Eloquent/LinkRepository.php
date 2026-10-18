<?php

namespace App\Repositories\Eloquent;

use App\Enums\LinkStatus;
use App\Models\Link;
use App\Repositories\Contracts\LinkRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class LinkRepository extends BaseRepository implements LinkRepositoryInterface
{
    public function __construct(Link $model)
    {
        parent::__construct($model);
    }

    public function findBySlug(string $slug): ?Link
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->model->newQuery()->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function getForUser(int $userId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->where('user_id', $userId)
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%")
                    ->orWhere('original_url', 'ilike', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function getAllForAdmin(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with('user:id,name,email')
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%")
                    ->orWhere('original_url', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function countForUser(int $userId, ?string $status = null): int
    {
        $query = $this->model->newQuery()->where('user_id', $userId);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function countAll(?string $status = null): int
    {
        $query = $this->model->newQuery();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->count();
    }
}
