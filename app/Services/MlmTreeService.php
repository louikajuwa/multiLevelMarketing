<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lazy, paginated tree loading via the materialized path. Never loads the whole
 * tree — only a page of roots, of one parent's children, or of flat search hits.
 * Every method returns ['data' => nodes[], 'meta' => paging].
 */
class MlmTreeService
{
    private const PER_PAGE = 25;

    /** Root nodes. Admin => paged depth-1 partners; a scoped partner => just themselves. */
    public function roots(?Partner $scope, int $page = 1): array
    {
        if ($scope) {
            return $this->result([$this->partnerNode($scope->loadCount('customers'))], 1, 1);
        }

        return $this->window(Partner::query()->where('depth', 1), null, $page);
    }

    /** Direct children of a partner (paged): sub-partners first, then customers. */
    public function children(Partner $parent, int $page = 1): array
    {
        return $this->window(
            Partner::query()->where('parent_id', $parent->id),
            Customer::query()->where('partner_id', $parent->id),
            $page,
        );
    }

    /** Flat, paged search by user name/email. Scoped partner => own subtree only. */
    public function search(string $term, ?Partner $scope, int $page = 1): array
    {
        $like = '%'.$term.'%';
        $nameMatch = fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like);

        $partners = Partner::query()->whereHas('user', $nameMatch);
        $customers = Customer::query()->whereHas('user', $nameMatch);

        if ($scope) {
            $subtree = $scope->path.$scope->id.'/%';
            $partners->where(fn (Builder $x) => $x->where('id', $scope->id)->orWhere('path', 'like', $subtree));
            $customers->whereHas('partner', fn (Builder $p) => $p->where('id', $scope->id)->orWhere('path', 'like', $subtree));
        }

        return $this->window($partners, $customers, $page);
    }

    /**
     * One logical page over the concatenation [partners… then customers…], so a
     * huge sibling set never loads at once.
     */
    private function window(Builder $partnerQuery, ?Builder $customerQuery, int $page): array
    {
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);

        $pCount = (clone $partnerQuery)->count();
        $cCount = $customerQuery ? (clone $customerQuery)->count() : 0;

        $items = [];
        if ($offset < $pCount) {
            $partners = $partnerQuery->with(['user:id,name', 'level:id,name'])->withCount('customers')
                ->orderBy('id')->skip($offset)->take($perPage)->get();
            foreach ($partners as $p) {
                $items[] = $this->partnerNode($p);
            }
            $remaining = $perPage - $partners->count();
            if ($remaining > 0 && $customerQuery) {
                foreach ($customerQuery->with('user:id,name')->orderBy('id')->take($remaining)->get() as $c) {
                    $items[] = $this->customerNode($c);
                }
            }
        } elseif ($customerQuery) {
            $customers = $customerQuery->with('user:id,name')->orderBy('id')->skip($offset - $pCount)->take($perPage)->get();
            foreach ($customers as $c) {
                $items[] = $this->customerNode($c);
            }
        }

        return $this->result($items, $page, (int) max(1, ceil(($pCount + $cCount) / $perPage)), $pCount + $cCount, $perPage);
    }

    private function result(array $data, int $page, int $lastPage, ?int $total = null, int $perPage = self::PER_PAGE): array
    {
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total ?? count($data),
                'has_more' => $page < $lastPage,
            ],
        ];
    }

    /** Whether $viewer (a partner) may inspect $parent's children (self or descendant). */
    public function canAccess(Partner $viewer, Partner $parent): bool
    {
        return $parent->id === $viewer->id
            || str_starts_with($parent->path, $viewer->path.$viewer->id.'/');
    }

    private function partnerNode(Partner $p): array
    {
        $hasChildren = $p->direct_referral_count > 0 || ($p->customers_count ?? 0) > 0;

        return [
            'id' => $p->id,
            'type' => 'partner',
            'name' => $p->user->name,
            'level' => $p->level?->name,
            'depth' => $p->depth,
            'direct' => $p->direct_referral_count,
            'downline' => $p->total_downline_count,
            'customers' => $p->customers_count ?? 0,
            'has_children' => $hasChildren,
        ];
    }

    private function customerNode(Customer $c): array
    {
        return [
            'id' => $c->id,
            'type' => 'customer',
            'name' => $c->user->name,
            'discount' => (float) $c->discount_percent,
            'has_children' => false,
        ];
    }
}
