<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected array $searchable = ['name', 'sku'];

    protected array $sortable = ['id', 'name', 'sku', 'retail_price', 'stock', 'is_active', 'created_at'];

    protected array $filterable = ['is_active', 'category_id', 'brand_id'];

    protected string $defaultSort = 'name';

    protected string $defaultDir = 'asc';

    public function model(): string
    {
        return Product::class;
    }

    protected function applySearch(Builder $query, array $params): void
    {
        $term = is_scalar($params['search'] ?? null) ? trim((string) $params['search']) : '';
        if ($term === '') {
            return;
        }
        $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
        });
    }
}
