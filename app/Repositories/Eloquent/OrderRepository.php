<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    protected array $searchable = ['order_number'];

    protected array $sortable = ['id', 'order_number', 'status', 'grand_total', 'placed_at', 'created_at'];

    protected array $filterable = ['status', 'partner_id', 'customer_id'];

    protected string $defaultSort = 'id';

    public function model(): string
    {
        return Order::class;
    }

    protected function applySearch(Builder $query, array $params): void
    {
        $term = trim((string) ($params['search'] ?? ''));
        if ($term === '') {
            return;
        }
        $query->where('order_number', 'like', "%{$term}%")
            ->orWhereHas('customer.user', fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
    }
}
