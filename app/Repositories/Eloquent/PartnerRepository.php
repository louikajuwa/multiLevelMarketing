<?php

namespace App\Repositories\Eloquent;

use App\Models\Partner;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PartnerRepository extends BaseRepository implements PartnerRepositoryInterface
{
    protected array $sortable = ['id', 'depth', 'direct_referral_count', 'total_downline_count', 'status', 'created_at'];

    protected array $filterable = ['status', 'partner_level_id'];

    protected string $defaultSort = 'id';

    public function model(): string
    {
        return Partner::class;
    }

    protected function applyFilters(Builder $query, array $params): void
    {
        parent::applyFilters($query, $params);

        // Server-injected (never user input: query strings are strings/arrays only).
        if (($params['_viewer'] ?? null) instanceof User) {
            $query->visibleTo($params['_viewer']);
        }
    }

    /** Search matches the partner's user name/email. */
    protected function applySearch(Builder $query, array $params): void
    {
        $term = is_scalar($params['search'] ?? null) ? trim((string) $params['search']) : '';
        if ($term === '') {
            return;
        }

        $query->whereHas('user', function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
        });
    }
}
