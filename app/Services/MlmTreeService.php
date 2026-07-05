<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Partner;

/**
 * Lazy tree loading via the materialized path. Never loads the whole tree —
 * only the roots, or one parent's direct children (partners + customers) on demand.
 */
class MlmTreeService
{
    /** Root nodes. Admin => depth-1 partners; a scoped partner => just themselves. */
    public function roots(?Partner $scope): array
    {
        if ($scope) {
            return [$this->partnerNode($scope->loadCount('customers'))];
        }

        return Partner::with(['user:id,name', 'level:id,name'])
            ->withCount('customers')
            ->where('depth', 1)
            ->get()
            ->map(fn (Partner $p) => $this->partnerNode($p))
            ->all();
    }

    /** Direct children of a partner: sub-partners then customers (leaf). */
    public function children(Partner $parent): array
    {
        $partners = Partner::with(['user:id,name', 'level:id,name'])
            ->withCount('customers')
            ->where('parent_id', $parent->id)
            ->get()
            ->map(fn (Partner $p) => $this->partnerNode($p));

        $customers = Customer::with('user:id,name')
            ->where('partner_id', $parent->id)
            ->get()
            ->map(fn (Customer $c) => $this->customerNode($c));

        return $partners->concat($customers)->values()->all();
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
