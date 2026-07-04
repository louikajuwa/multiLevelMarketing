<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;

class BrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'is_active', 'created_at'];

    protected array $filterable = ['is_active'];

    protected string $defaultSort = 'name';

    protected string $defaultDir = 'asc';

    public function model(): string
    {
        return Brand::class;
    }
}
