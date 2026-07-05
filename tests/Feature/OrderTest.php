<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\PartnerLevel;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function partnerWithCustomer(float $levelDiscount, float $custDiscount): Customer
    {
        $level = PartnerLevel::factory()->create(['discount_percent' => $levelDiscount]);
        $partner = app(PartnerService::class)->register([
            'name' => 'P'.uniqid(), 'email' => uniqid().'@x.test', 'password' => 'password123',
            'partner_level_id' => $level->id,
        ]);

        return Customer::factory()->create(['partner_id' => $partner->id, 'discount_percent' => $custDiscount]);
    }

    public function test_place_order_prices_lines_and_decrements_stock(): void
    {
        $customer = $this->partnerWithCustomer(levelDiscount: 10, custDiscount: 5);
        $product = Product::factory()->create(['retail_price' => 100, 'stock' => 10, 'partner_discount' => null]);

        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 2]]);

        // eff discount = min(cust 5, partner 10) = 5% -> final 95, line 190
        $this->assertSame('190.00', $order->subtotal);
        $this->assertSame('10.00', $order->discount_total);
        $this->assertSame('190.00', $order->grand_total);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending', 'amount' => 190.00]);
    }

    public function test_product_discount_cap_applies(): void
    {
        $customer = $this->partnerWithCustomer(levelDiscount: 20, custDiscount: 15);
        $product = Product::factory()->create(['retail_price' => 200, 'stock' => 5, 'partner_discount' => 3]);

        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);

        // eff = min(cust 15, partner 20, product 3) = 3% -> final 194
        $this->assertSame('194.00', $order->items->first()->unit_final_price);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        $customer = $this->partnerWithCustomer(10, 0);
        $product = Product::factory()->create(['retail_price' => 50, 'stock' => 1]);

        $this->expectException(ValidationException::class);
        app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 5]]);

        $this->assertSame(1, $product->fresh()->stock); // unchanged (rolled back)
    }

    public function test_mark_paid_settles_payment(): void
    {
        $customer = $this->partnerWithCustomer(10, 0);
        $product = Product::factory()->create(['retail_price' => 50, 'stock' => 5]);
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);

        app(OrderService::class)->setStatus($order, OrderStatus::Paid);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
    }

    public function test_admin_can_place_order_via_http(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $customer = $this->partnerWithCustomer(10, 5);
        $product = Product::factory()->create(['retail_price' => 100, 'stock' => 10]);

        $this->actingAs($admin)->postJson(route('orders.store'), [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.grand_total', '95.00');
    }

    public function test_partner_cannot_order_for_another_partners_customer(): void
    {
        $mine = $this->partnerWithCustomer(10, 0);
        $other = $this->partnerWithCustomer(10, 0);
        $product = Product::factory()->create(['retail_price' => 10, 'stock' => 5]);

        $this->actingAs($mine->partner->user)->postJson(route('orders.store'), [
            'customer_id' => $other->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();
    }
}
