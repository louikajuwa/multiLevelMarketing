<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;

/**
 * Builds tabular report datasets: { title, columns[], rows[][] }.
 * Rows are plain scalar arrays so they export cleanly to Excel / CSV / PDF.
 * A non-null $partnerId scopes the data to that partner (for partner users).
 */
class ReportService
{
    private const PAID = ['paid', 'processing', 'shipped', 'completed'];

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
            default => ['title' => 'Report', 'columns' => [], 'rows' => []],
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
        $q = Order::with('customer.user', 'partner.user')->whereIn('status', self::PAID);
        $pid && $q->where('partner_id', $pid);
        $this->dateScope($q, $f, 'placed_at');

        return [
            'title' => 'Sales Report',
            'columns' => ['Order', 'Customer', 'Partner', 'Total', 'Date'],
            'rows' => $q->latest('placed_at')->get()->map(fn (Order $o) => [
                $o->order_number, $o->customer?->user?->name, $o->partner?->user?->name,
                number_format((float) $o->grand_total, 2), (string) $o->placed_at?->toDateString(),
            ])->all(),
        ];
    }

    private function commissions(array $f, ?int $pid): array
    {
        $q = Commission::with('partner.user', 'order');
        $pid && $q->where('partner_id', $pid);
        $this->dateScope($q, $f, 'created_at');

        return [
            'title' => 'Commission Report',
            'columns' => ['Partner', 'Type', 'Order', 'Percent', 'Amount', 'Status', 'Date'],
            'rows' => $q->latest('id')->get()->map(fn (Commission $c) => [
                $c->partner?->user?->name, $c->type->value, $c->order?->order_number,
                number_format((float) $c->percent, 2), number_format((float) $c->amount, 2),
                $c->status->value, (string) $c->created_at?->toDateString(),
            ])->all(),
        ];
    }

    private function partners(?int $pid): array
    {
        $q = Partner::with('user', 'level');
        $pid && $q->where(fn ($x) => $x->where('id', $pid)->orWhere('path', 'like', '%/'.$pid.'/%'));

        return [
            'title' => 'Partner Report',
            'columns' => ['Name', 'Email', 'Level', 'Depth', 'Direct', 'Downline', 'Status'],
            'rows' => $q->get()->map(fn (Partner $p) => [
                $p->user?->name, $p->user?->email, $p->level?->name, $p->depth,
                $p->direct_referral_count, $p->total_downline_count, $p->status,
            ])->all(),
        ];
    }

    private function customers(?int $pid): array
    {
        $q = Customer::with('user', 'partner.user');
        $pid && $q->where('partner_id', $pid);

        return [
            'title' => 'Customer Report',
            'columns' => ['Name', 'Email', 'Partner', 'Discount %', 'Status'],
            'rows' => $q->get()->map(fn (Customer $c) => [
                $c->user?->name, $c->user?->email, $c->partner?->user?->name,
                number_format((float) $c->discount_percent, 2), $c->status,
            ])->all(),
        ];
    }

    private function orders(array $f, ?int $pid): array
    {
        $q = Order::with('customer.user');
        $pid && $q->where('partner_id', $pid);
        $this->dateScope($q, $f, 'placed_at');

        return [
            'title' => 'Order Report',
            'columns' => ['Order', 'Customer', 'Status', 'Total', 'Date'],
            'rows' => $q->latest('id')->get()->map(fn (Order $o) => [
                $o->order_number, $o->customer?->user?->name, $o->status->value,
                number_format((float) $o->grand_total, 2), (string) $o->placed_at?->toDateString(),
            ])->all(),
        ];
    }

    private function inventory(): array
    {
        return [
            'title' => 'Inventory Report',
            'columns' => ['SKU', 'Product', 'Category', 'Brand', 'Stock', 'Retail', 'Status'],
            'rows' => Product::with('category', 'brand')->orderBy('name')->get()->map(fn (Product $p) => [
                $p->sku, $p->name, $p->category?->name, $p->brand?->name, $p->stock,
                number_format((float) $p->retail_price, 2), $p->is_active ? 'Active' : 'Inactive',
            ])->all(),
        ];
    }
}
