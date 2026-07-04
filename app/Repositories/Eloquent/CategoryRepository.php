<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'is_active', 'created_at'];

    protected array $filterable = ['is_active', 'parent_id'];

    protected string $defaultSort = 'name';

    protected string $defaultDir = 'asc';

    public function model(): string
    {
        return Category::class;
    }
}
