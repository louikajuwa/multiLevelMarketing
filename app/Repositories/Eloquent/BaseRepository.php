<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic Eloquent repository. Subclasses declare the model + which columns are
 * searchable / sortable / filterable, and get pagination + search + sort +
 * filter for free (DRY across every listing endpoint).
 *
 *   list flow:  Controller -> Service/Action -> Repository::paginate($params)
 *   $params:    search, sort_by, sort_dir, per_page, page, + filterable keys
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @var string[] columns matched by the `search` param (LIKE) */
    protected array $searchable = [];

    /** @var string[] columns allowed for `sort_by` (whitelist — prevents SQL injection via sort) */
    protected array $sortable = ['id'];

    /** @var string[] columns allowed as exact-match filters (from params of the same name) */
    protected array $filterable = [];

    protected string $defaultSort = 'id';

    protected string $defaultDir = 'desc';

    protected int $defaultPerPage = 15;

    protected int $maxPerPage = 100;

    /** @return class-string<Model> */
    abstract public function model(): string;

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query();
    }

    public function find(int|string $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    public function findOrFail(int|string $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function paginate(array $params = [], array $with = []): LengthAwarePaginator
    {
        $query = $this->query()->with($with);

        $this->applySearch($query, $params);
        $this->applyFilters($query, $params);
        $this->applySort($query, $params);

        return $query->paginate($this->resolvePerPage($params))->withQueryString();
    }

    protected function applySearch(Builder $query, array $params): void
    {
        $term = trim((string) ($params['search'] ?? ''));
        if ($term === '' || empty($this->searchable)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            foreach ($this->searchable as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    protected function applyFilters(Builder $query, array $params): void
    {
        foreach ($this->filterable as $column) {
            if (array_key_exists($column, $params) && $params[$column] !== null && $params[$column] !== '') {
                $query->where($column, $params[$column]);
            }
        }
    }

    protected function applySort(Builder $query, array $params): void
    {
        $sortBy = $params['sort_by'] ?? $this->defaultSort;
        if (! in_array($sortBy, $this->sortable, true)) {
            $sortBy = $this->defaultSort;
        }

        $dir = strtolower((string) ($params['sort_dir'] ?? $this->defaultDir)) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $dir);
    }

    protected function resolvePerPage(array $params): int
    {
        $perPage = (int) ($params['per_page'] ?? $this->defaultPerPage);
        if ($perPage < 1) {
            $perPage = $this->defaultPerPage;
        }

        return min($perPage, $this->maxPerPage);
    }
}
