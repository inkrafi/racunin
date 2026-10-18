<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    public function all(array $columns = ['*']): Collection;

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    public function findById(int|string $id, array $columns = ['*']): ?Model;

    public function create(array $attributes): Model;

    public function update(int|string $id, array $attributes): bool;

    public function delete(int|string $id): bool;
}
