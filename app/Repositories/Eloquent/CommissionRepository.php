<?php

namespace App\Repositories\Eloquent;

use App\Models\Commission;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CommissionRepository extends BaseRepository implements CommissionRepositoryInterface
{
    protected array $sortable = ['id', 'amount', 'type', 'status', 'created_at'];

    protected array $filterable = ['partner_id', 'type', 'status'];

    protected string $defaultSort = 'id';

    public function model(): string
    {
        return Commission::class;
    }

    protected function applySearch(Builder $query, array $params): void
    {
        $term = trim((string) ($params['search'] ?? ''));
        if ($term === '') {
            return;
        }
        $query->whereHas('partner.user', fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
    }
}
