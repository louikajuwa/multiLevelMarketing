<?php

namespace App\Repositories\Eloquent;

use App\Models\Partner;
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

    /** Search matches the partner's user name/email. */
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
