<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(private readonly PartnerService $partners) {}

    /**
     * Register a customer under a partner (creates the user + customer row).
     *
     * @param  array{name:string,email:string,password:string,partner_id:int,discount_percent?:float|null,phone?:string|null,address?:string|null}  $data
     */
    public function register(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->assignRole(RoleName::Customer->value);

            return Customer::create([
                'user_id' => $user->id,
                'partner_id' => $data['partner_id'],
                'discount_percent' => $this->cappedDiscount((int) $data['partner_id'], (float) ($data['discount_percent'] ?? 0)),
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'active',
            ])->load(['user', 'partner.user']);
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill([
            'discount_percent' => $this->cappedDiscount($customer->partner_id, (float) ($data['discount_percent'] ?? 0)),
            'phone' => $data['phone'] ?? $customer->phone,
            'address' => $data['address'] ?? $customer->address,
            'status' => $data['status'] ?? $customer->status,
        ])->save();

        return $customer->refresh()->load(['user', 'partner.user']);
    }

    /** Defensive server-side cap: a customer discount can never exceed the partner's effective discount. */
    public function cappedDiscount(int $partnerId, float $requested): float
    {
        $partner = Partner::with('level')->find($partnerId);
        $cap = $partner ? $this->partners->effectiveDiscountPercent($partner) : 0.0;

        return min(max($requested, 0.0), $cap);
    }
}
