<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // --- Catalog ---
        $brands = Brand::factory(5)->create();
        $categories = Category::factory(6)->create();
        Product::factory(30)->recycle($brands)->recycle($categories)->create();

        // --- Partner tree (manual path/depth here; real registration uses PartnerService) ---
        $levels = PartnerLevel::orderBy('level')->get()->keyBy('level');

        $makePartner = function (string $name, string $email, int $level, ?Partner $parent) use ($levels): Partner {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'is_active' => true],
            );
            $user->syncRoles([RoleName::Partner->value]);

            return Partner::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'partner_level_id' => $levels[$level]->id,
                    'sponsor_id' => $parent?->id,
                    'parent_id' => $parent?->id,
                    'path' => $parent ? $parent->path.$parent->id.'/' : '/',
                    'depth' => $parent ? $parent->depth + 1 : 1,
                    'status' => 'active',
                ],
            );
        };

        $alice = $makePartner('Alice (L4)', 'alice@mlm.test', 4, null);
        $bob = $makePartner('Bob (L3)', 'bob@mlm.test', 3, $alice);
        $carol = $makePartner('Carol (L2)', 'carol@mlm.test', 2, $bob);
        $dan = $makePartner('Dan (L1)', 'dan@mlm.test', 1, $carol);
        $eve = $makePartner('Eve (L2)', 'eve@mlm.test', 2, $alice);

        // --- Customers ---
        $makeCustomer = function (string $name, string $email, Partner $partner, float $discount = 0): void {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'is_active' => true],
            );
            $user->syncRoles([RoleName::Customer->value]);

            Customer::updateOrCreate(
                ['user_id' => $user->id],
                ['partner_id' => $partner->id, 'discount_percent' => $discount, 'status' => 'active'],
            );
        };

        $makeCustomer('Customer One', 'cust1@mlm.test', $bob, 5);
        $makeCustomer('Customer Two', 'cust2@mlm.test', $carol, 10);
        $makeCustomer('Customer Three', 'cust3@mlm.test', $dan, 0);
        $makeCustomer('Customer Four', 'cust4@mlm.test', $eve, 8);

        // --- Recompute denormalized downline counts ---
        foreach (Partner::all() as $partner) {
            $partner->update([
                'direct_referral_count' => Partner::where('parent_id', $partner->id)->count(),
                'total_downline_count' => Partner::where('path', 'like', $partner->path.$partner->id.'/%')->count(),
            ]);
        }

        // --- Demo orders (a couple per customer, some marked paid) ---
        $orders = app(OrderService::class);
        $pool = Product::inStock()->take(4)->get();
        if ($pool->isNotEmpty()) {
            foreach (Customer::with('partner.level', 'user')->get() as $i => $cust) {
                $picks = $pool->random(min(2, $pool->count()));
                $lines = $picks->map(fn (Product $p) => ['product_id' => $p->id, 'quantity' => 1])->all();
                $order = $orders->place($cust, $lines);
                if ($i % 2 === 0) {
                    $orders->setStatus($order, OrderStatus::Paid);
                }
            }
        }
    }
}
