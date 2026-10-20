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

        $isCustomer = ! $user->isStaff() && ! $user->partner && $user->customer;

        return view('orders.index', [
            'canPickCustomer' => ! $isCustomer,       // staff + partners choose a customer; a customer orders for self
            'selfCustomerId' => $user->customer?->id,
            'statuses' => OrderStatus::values(),
        ]);
    }

    /** Searchable customer options, scoped to who the user may see. */
    public function customerOptions(Request $request): mixed
    {
        $term = (is_string($request->get('search')) ? trim($request->get('search')) : '');
        $q = Customer::query()->visibleTo($request->user())->with('user:id,name');
        if ($term !== '') {
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return $this->paginate($q->orderBy('id')->paginate($this->optionLimit($request), ['id', 'user_id', 'partner_id']));
    }

    /** Searchable product options (active + in stock). */
    public function productOptions(Request $request): mixed
    {
        $term = (is_string($request->get('search')) ? trim($request->get('search')) : '');
        $q = Product::query()->active()->inStock();
        if ($term !== '') {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }

        return $this->paginate($q->orderBy('name')->paginate($this->optionLimit($request), ['id', 'name', 'sku', 'retail_price', 'stock']));
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
        abort_unless($this->canSee($request->user(), $order), 403);

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
}
