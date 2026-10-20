<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Partner;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerService $service,
    ) {}

    public function index(Request $request): mixed
    {
        $user = $request->user();
        $params = $request->all();

        // Partners see only their own customers.
        if (! $user->isStaff()) {
            $params['partner_id'] = $user->partner?->id ?? 0;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->customers->paginate($params, ['user', 'partner.user']));
        }

        return view('customers.index', ['isStaff' => $user->isStaff()]);
    }

    /** Searchable partner options for the create form (staff only). */
    public function partnerOptions(Request $request): mixed
    {
        $term = (is_string($request->get('search')) ? trim($request->get('search')) : '');
        $q = Partner::query()->visibleTo($request->user())->with('user:id,name');
        if ($term !== '') {
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return $this->paginate($q->orderBy('id')->paginate($this->optionLimit($request), ['id', 'user_id', 'depth']));
    }

    public function store(StoreCustomerRequest $request): mixed
    {
        return $this->created($this->service->register($request->validated()), 'Customer created.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): mixed
    {
        abort_unless($request->user()->can('update', $customer), 403);

        return $this->ok($this->service->update($customer, $request->validated()), 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): mixed
    {
        abort_unless($request->user()->can('delete', $customer), 403);

        if ($customer->orders()->exists()) {
            return $this->fail('Cannot delete a customer that has orders.', 422);
        }

        $user = $customer->user;
        $customer->delete();
        $user?->delete();

        return $this->ok(null, 'Customer deleted.');
    }
}
