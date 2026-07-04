<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function find(int|string $id, array $with = []): ?Model;

    public function findOrFail(int|string $id, array $with = []): Model;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;

    /**
     * Paginated list with search / filter / sort support.
     *
     * @param  array<string,mixed>  $params  search, sort_by, sort_dir, per_page, page + filterable keys
     * @param  string[]  $with  relations to eager-load
     */
    public function paginate(array $params = [], array $with = []): LengthAwarePaginator;
}
