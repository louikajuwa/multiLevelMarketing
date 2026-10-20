<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array{0:Partner,1:Partner,2:Customer,3:Product} */
    private function scenario(): array
    {
        $svc = app(PartnerService::class);
        $lvlA = PartnerLevel::factory()->create(['discount_percent' => 0, 'commission_percent' => 10, 'sponsor_bonus_percent' => 5]);
        $lvlB = PartnerLevel::factory()->create(['discount_percent' => 0, 'commission_percent' => 8, 'sponsor_bonus_percent' => 5]);
        $sponsor = $svc->register(['name' => 'Spon', 'email' => 's@x.test', 'password' => 'password123', 'partner_level_id' => $lvlA->id]);
        $child = $svc->register(['name' => 'Child', 'email' => 'c@x.test', 'password' => 'password123', 'partner_level_id' => $lvlB->id, 'sponsor_id' => $sponsor->id]);
        $customer = Customer::factory()->create(['partner_id' => $child->id, 'discount_percent' => 0]);
        $product = Product::factory()->create(['retail_price' => 100, 'stock' => 10]);

        return [$sponsor, $child, $customer, $product];
    }

    private function order(Customer $c, Product $p, int $qty = 2): Order
    {
        return app(OrderService::class)->place($c, [['product_id' => $p->id, 'quantity' => $qty]]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_illegal_transition_rejected_with_422(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product);

        $this->actingAs($this->admin())->putJson(route('orders.status', $order), ['status' => 'shipped'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_same_status_rejected(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product);

        $this->actingAs($this->admin())->putJson(route('orders.status', $order), ['status' => 'pending'])
            ->assertStatus(422);
    }

    public function test_pending_to_paid_generates_commissions_once_and_paid_twice_rejected(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product);
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(route('orders.status', $order), ['status' => 'paid'])->assertOk();
        $this->assertSame(2, Commission::where('order_id', $order->id)->count());

        $this->actingAs($admin)->putJson(route('orders.status', $order), ['status' => 'paid'])->assertStatus(422);
        $this->assertSame(2, Commission::where('order_id', $order->id)->count());
    }

    public function test_cancel_from_pending_restocks_and_fails_payment(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product, 3);
        $this->assertSame(7, $product->fresh()->stock);

        app(OrderService::class)->setStatus($order, OrderStatus::Cancelled);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    }

    public function test_cancel_after_paid_voids_pending_commissions_and_restocks(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product, 2);
        app(OrderService::class)->setStatus($order, OrderStatus::Paid);
        app(OrderService::class)->setStatus($order, OrderStatus::Cancelled);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(2, Commission::where('order_id', $order->id)->where('status', 'void')->count());
        $this->assertSame(0, Commission::where('order_id', $order->id)->where('status', 'pending')->count());
    }

    public function test_cancel_leaves_paid_commissions_alone(): void
    {
        [, $child, $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product, 1);
        app(OrderService::class)->setStatus($order, OrderStatus::Paid);
        Commission::where('order_id', $order->id)->where('partner_id', $child->id)->update(['status' => 'paid']);

        app(OrderService::class)->setStatus($order, OrderStatus::Cancelled);

        $this->assertDatabaseHas('commissions', ['order_id' => $order->id, 'partner_id' => $child->id, 'status' => 'paid']);
    }

    public function test_completed_is_terminal(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product);
        $svc = app(OrderService::class);
        foreach ([OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed] as $s) {
            $svc->setStatus($order, $s);
        }

        foreach (OrderStatus::cases() as $s) {
            $this->assertFalse(OrderStatus::Completed->canTransitionTo($s));
        }
        $this->expectException(ValidationException::class);
        $svc->setStatus($order, OrderStatus::Cancelled);
    }

    public function test_partner_without_permission_gets_403(): void
    {
        [, $child, $customer, $product] = $this->scenario();
        $order = $this->order($customer, $product);

        $this->actingAs($child->user)->putJson(route('orders.status', $order), ['status' => 'paid'])->assertForbidden();
    }

    public function test_dashboard_earnings_exclude_voided_commissions(): void
    {
        [, $child, $customer, $product] = $this->scenario();
        $svc = app(OrderService::class);
        $kept = $this->order($customer, $product, 1);
        $svc->setStatus($kept, OrderStatus::Paid);   // child earns 8.00
        $gone = $this->order($customer, $product, 1);
        $svc->setStatus($gone, OrderStatus::Paid);   // another 8.00
        $svc->setStatus($gone, OrderStatus::Cancelled); // voided

        $child->user->assignRole(RoleName::Partner->value);
        $this->actingAs($child->user)->get('/dashboard')->assertOk()
            ->assertSee('$8.00')->assertDontSee('$16.00');
    }
}
