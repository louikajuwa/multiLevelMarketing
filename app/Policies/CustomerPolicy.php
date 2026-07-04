<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /** A partner may only touch customers under their own partner account; staff see all. */
    private function owns(User $user, Customer $customer): bool
    {
        return $user->isStaff() || ($user->partner && $customer->partner_id === $user->partner->id);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $this->owns($user, $customer);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $this->owns($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete') && $this->owns($user, $customer);
    }
}
