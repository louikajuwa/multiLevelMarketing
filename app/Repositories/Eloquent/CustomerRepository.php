<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected array $sortable = ['id', 'discount_percent', 'status', 'created_at'];

    protected array $filterable = ['status', 'partner_id'];

    protected string $defaultSort = 'id';

    public function model(): string
    {
        return Customer::class;
    }

    /** Search matches the customer's user name/email. */
    protected function applySearch(Builder $query, array $params): void
    {
        $term = trim((string) ($params['search'] ?? ''));
        if ($term === '') {
            return;
        }

        $query->whereHas('user', function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
        });
    }
}
