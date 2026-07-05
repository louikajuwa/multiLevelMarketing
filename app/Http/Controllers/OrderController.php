<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderService $service,
    ) {}

    public function index(Request $request): mixed
    {
        $user = $request->user();
        $params = $request->all();

        if (! $user->isStaff()) {
            if ($user->partner) {
                $params['partner_id'] = $user->partner->id;
            } elseif ($user->customer) {
                $params['customer_id'] = $user->customer->id;
            } else {
                $params['customer_id'] = 0;
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->orders->paginate($params, ['customer.user', 'partner.user']));
        }

        return view('orders.index', [
            'isStaff' => $user->isStaff(),
            'customers' => $this->customerOptions($user),
            'products' => Product::active()->inStock()->orderBy('name')->get(['id', 'name', 'sku', 'retail_price', 'stock']),
            'statuses' => OrderStatus::values(),
        ]);
    }

    public function store(StoreOrderRequest $request): mixed
    {
        $user = $request->user();
        $customer = $this->resolveCustomer($user, (int) $request->input('customer_id'));

        $order = $this->service->place(
            $customer,
            $request->input('items'),
            $request->only('shipping_name', 'shipping_address', 'shipping_phone'),
        );

        return $this->created($order, 'Order placed.');
    }

    public function show(Request $request, Order $order): mixed
    {
        abort_unless($this->canSee($request->user(), $order), 403);

        return $this->ok($order->load(['items', 'customer.user', 'partner.user', 'payments']));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): mixed
    {
        $order = $this->service->setStatus($order, OrderStatus::from($request->string('status')->value()));

        return $this->ok($order, 'Order status updated.');
    }

    private function resolveCustomer($user, int $customerId): Customer
    {
        if (! $user->isStaff() && $user->customer) {
            return $user->customer;
        }

        $customer = Customer::findOrFail($customerId);

        if (! $user->isStaff() && $user->partner) {
            abort_unless($customer->partner_id === $user->partner->id, 403, 'That customer is not in your network.');
        }

        return $customer;
    }

    private function canSee($user, Order $order): bool
    {
        if ($user->isStaff()) {
            return true;
        }
        if ($user->partner) {
            return $order->partner_id === $user->partner->id;
        }
        if ($user->customer) {
            return $order->customer_id === $user->customer->id;
        }

        return false;
    }

    private function customerOptions($user): Collection
    {
        $query = Customer::with('user:id,name');
        if (! $user->isStaff() && $user->partner) {
            $query->where('partner_id', $user->partner->id);
        }

        return $query->get(['id', 'user_id'])->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->user->name]);
    }
}
