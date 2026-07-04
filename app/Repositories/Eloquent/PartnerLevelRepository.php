<?php

namespace App\Repositories\Eloquent;

use App\Models\PartnerLevel;
use App\Repositories\Contracts\PartnerLevelRepositoryInterface;

class PartnerLevelRepository extends BaseRepository implements PartnerLevelRepositoryInterface
{
    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'level', 'discount_percent', 'commission_percent', 'sponsor_bonus_percent', 'is_active', 'created_at'];

    protected array $filterable = ['is_active'];

    protected string $defaultSort = 'level';

    protected string $defaultDir = 'asc';

    public function model(): string
    {
        return PartnerLevel::class;
    }
}
