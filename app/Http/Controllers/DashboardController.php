<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /** Order statuses that count as recognized revenue. */
    private const PAID = ['paid', 'processing', 'shipped', 'completed'];

    public function index(): mixed
    {
        $user = auth()->user();

        $data = match (true) {
            $user->hasRole(RoleName::Partner->value) && $user->partner => $this->partner($user->partner),
            $user->hasRole(RoleName::Customer->value) && $user->customer => $this->customer($user->customer),
            default => $this->admin(),
        };

        return view('dashboard', array_merge(['user' => $user, 'role' => $data['role']], $data));
    }

    private function admin(): array
    {
        $revenue = (float) Order::whereIn('status', self::PAID)->sum('grand_total');

        return [
            'role' => 'admin',
            'kpis' => [
                ['label' => 'Revenue', 'value' => $this->money($revenue)],
                ['label' => 'Partners', 'value' => number_format(Partner::count())],
                ['label' => 'Customers', 'value' => number_format(Customer::count())],
                ['label' => 'Orders', 'value' => number_format(Order::count())],
            ],
            'secondary' => [
                'Active partners' => Partner::where('status', 'active')->count(),
                'Products' => Product::count(),
                'Pending orders' => Order::where('status', 'pending')->count(),
                'Commissions paid' => $this->money((float) Commission::sum('amount')),
            ],
            'chart' => $this->monthlyRevenue(null),
            'topPartners' => Partner::with('user:id,name')
                ->withSum('commissions as earned', 'amount')
                ->orderByDesc('earned')->take(5)->get()
                ->map(fn ($p) => ['name' => $p->user->name, 'value' => $this->money((float) $p->earned)]),
            'recentOrders' => $this->recent(Order::query()),
        ];
    }

    private function partner(Partner $partner): array
    {
        $sales = (float) Order::where('partner_id', $partner->id)->whereIn('status', self::PAID)->sum('grand_total');

        return [
            'role' => 'partner',
            'kpis' => [
                ['label' => 'My earnings', 'value' => $this->money((float) Commission::where('partner_id', $partner->id)->sum('amount'))],
                ['label' => 'My sales', 'value' => $this->money($sales)],
                ['label' => 'My customers', 'value' => number_format($partner->customers()->count())],
                ['label' => 'Downline', 'value' => number_format($partner->total_downline_count)],
            ],
            'secondary' => [
                'Direct referrals' => $partner->direct_referral_count,
                'My orders' => Order::where('partner_id', $partner->id)->count(),
                'Level' => $partner->level?->name ?? '—',
            ],
            'chart' => $this->monthlyRevenue($partner->id),
            'topPartners' => collect(),
            'recentOrders' => $this->recent(Order::where('partner_id', $partner->id)),
        ];
    }

    private function customer(Customer $customer): array
    {
        return [
            'role' => 'customer',
            'kpis' => [
                ['label' => 'My discount', 'value' => rtrim(rtrim(number_format((float) $customer->discount_percent, 2), '0'), '.').'%'],
                ['label' => 'My orders', 'value' => number_format($customer->orders()->count())],
                ['label' => 'Spent', 'value' => $this->money((float) $customer->orders()->whereIn('status', self::PAID)->sum('grand_total'))],
                ['label' => 'Partner', 'value' => $customer->partner?->user?->name ?? '—'],
            ],
            'secondary' => [],
            'chart' => null,
            'topPartners' => collect(),
            'recentOrders' => $this->recent(Order::where('customer_id', $customer->id)),
        ];
    }

    private function recent($query): Collection
    {
        return $query->with('customer.user')->latest('id')->take(6)->get()
            ->map(fn (Order $o) => [
                'number' => $o->order_number,
                'customer' => $o->customer?->user?->name ?? '—',
                'total' => $this->money((float) $o->grand_total),
                'status' => $o->status->value,
            ]);
    }

    /** Last 6 months of recognized revenue (optionally scoped to a partner). */
    private function monthlyRevenue(?int $partnerId): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        // Bucket in PHP (portable across MySQL/SQLite — no DB-specific date functions).
        $buckets = [];
        Order::query()
            ->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId))
            ->whereIn('status', self::PAID)
            ->where('placed_at', '>=', $start)
            ->get(['placed_at', 'grand_total'])
            ->each(function (Order $o) use (&$buckets): void {
                if ($o->placed_at) {
                    $k = $o->placed_at->format('Y-m');
                    $buckets[$k] = ($buckets[$k] ?? 0) + (float) $o->grand_total;
                }
            });

        $labels = [];
        $values = [];
        for ($i = 0; $i < 6; $i++) {
            $m = (clone $start)->addMonths($i);
            $labels[] = $m->format('M');
            $values[] = round((float) ($buckets[$m->format('Y-m')] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function money(float $v): string
    {
        return '$'.number_format($v, 2);
    }
}
