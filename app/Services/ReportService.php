<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;

/**
 * Builds report specs: { title, columns[], query, map }. The query is NOT
 * executed here — exporters stream it in chunks so a large report never
 * materializes every row in memory. A non-null $partnerId scopes to that partner.
 */
class ReportService
{
    public const TYPES = ['sales', 'commissions', 'partners', 'customers', 'orders', 'inventory'];

    public function build(string $type, array $filters = [], ?int $partnerId = null): array
    {
        return match ($type) {
            'sales' => $this->sales($filters, $partnerId),
            'commissions' => $this->commissions($filters, $partnerId),
            'partners' => $this->partners($partnerId),
            'customers' => $this->customers($partnerId),
            'orders' => $this->orders($filters, $partnerId),
            'inventory' => $this->inventory(),
            default => ['title' => 'Report', 'columns' => [], 'query' => Product::query()->whereRaw('1 = 0'), 'map' => fn () => []],
        };
    }

    private function dateScope($query, array $f, string $col)
    {
        if (! empty($f['from'])) {
            $query->whereDate($col, '>=', $f['from']);
        }
        if (! empty($f['to'])) {
            $query->whereDate($col, '<=', $f['to']);
        }

        return $query;
    }

    private function sales(array $f, ?int $pid): array
    {
        $q = Order::query()->with('customer.user', 'partner.user')->whereIn('status', OrderStatus::revenueStates());
        $pid && $q->where('partner_id', $pid);
        $this->dateScope($q, $f, 'placed_at');

        return [
            'title' => 'Sales Report',
            'columns' => ['Order', 'Customer', 'Partner', 'Total', 'Date'],
            'query' => $q->latest('placed_at'),
            'map' => fn (Order $o) => [
                $o->order_number, $o->customer?->user?->name, $o->partner?->user?->name,
                number_format((float) $o->grand_total, 2), (string) $o->placed_at?->toDateString(),
            ],
        ];
    }

    private function commissions(array $f, ?int $pid): array
    {
        $q = Commission::query()->with('partner.user', 'order');
        $pid && $q->where('partner_id', $pid);
        $this->dateScope($q, $f, 'created_at');

        return [
            'title' => 'Commission Report',
            'columns' => ['Partner', 'Type', 'Order', 'Percent', 'Amount', 'Status', 'Date'],
            'query' => $q->latest('id'),
            'map' => fn (Commission $c) => [
                $c->partner?->user?->name, $c->type->value, $c->order?->order_number,
                number_format((float) $c->percent, 2), number_format((float) $c->amount, 2),
                $c->status->value, (string) $c->created_at?->toDateString(),
            ],
        ];
    }

    private function partners(?int $pid): array
    {
        $q = Partner::query()->with('user', 'level');
        $pid && $q->where(fn ($x) => $x->where('id', $pid)->orWhere('path', 'like', '%/'.$pid.'/%'));

        return [
            'title' => 'Partner Report',
            'columns' => ['Name', 'Email', 'Level', 'Depth', 'Direct', 'Downline', 'Status'],
            'query' => $q->orderBy('id'),
            'map' => fn (Partner $p) => [
                $p->user?->name, $p->user?->email, $p->level?->name, $p->depth,
                $p->direct_referral_count, $p->total_downline_count, $p->status,
            ],
        ];
    }

    private function customers(?int $pid): array
    {
        $q = Customer::query()->with('user', 'partner.user');
        $pid && $q->where('partner_id', $pid);

        return [
            'title' => 'Customer Report',
            'columns' => ['Name', 'Email', 'Partner', 'Discount %', 'Status'],
            'query' => $q->orderBy('id'),
            'map' => fn (Customer $c) => [
                $c->user?->name, $c->user?->email, $c->partner?->user?->name,
                number_format((float) $c->discount_percent, 2), $c->status,
            ],
        ];
    }

    private function orders(array $f, ?int $pid): array
    {
        $q = Order::query()->with('customer.user');
        $pid && $q->where('partner_id', $pid);
        $this->dateScope($q, $f, 'placed_at');

        return [
            'title' => 'Order Report',
            'columns' => ['Order', 'Customer', 'Status', 'Total', 'Date'],
            'query' => $q->latest('id'),
            'map' => fn (Order $o) => [
                $o->order_number, $o->customer?->user?->name, $o->status->value,
                number_format((float) $o->grand_total, 2), (string) $o->placed_at?->toDateString(),
            ],
        ];
    }

    private function inventory(): array
    {
        return [
            'title' => 'Inventory Report',
            'columns' => ['SKU', 'Product', 'Category', 'Brand', 'Stock', 'Retail', 'Status'],
            'query' => Product::query()->with('category', 'brand')->orderBy('name'),
            'map' => fn (Product $p) => [
                $p->sku, $p->name, $p->category?->name, $p->brand?->name, $p->stock,
                number_format((float) $p->retail_price, 2), $p->is_active ? 'Active' : 'Inactive',
            ],
        ];
    }
}
