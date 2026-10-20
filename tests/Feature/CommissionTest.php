<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Commission;
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

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

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

    public function test_paid_order_generates_direct_and_sponsor_commissions(): void
    {
        [$sponsor, $child, $customer, $product] = $this->scenario();
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 2]]); // subtotal 200
        app(OrderService::class)->setStatus($order, OrderStatus::Paid);

        // direct: child level 8% of 200 = 16.00
        $this->assertDatabaseHas('commissions', ['order_id' => $order->id, 'partner_id' => $child->id, 'type' => 'direct', 'amount' => 16.00]);
        // sponsor bonus: sponsor level 5% of 16 = 0.80
        $this->assertDatabaseHas('commissions', ['order_id' => $order->id, 'partner_id' => $sponsor->id, 'type' => 'sponsor_bonus', 'amount' => 0.80]);
    }

    public function test_commission_generation_is_idempotent(): void
    {
        [, , $customer, $product] = $this->scenario();
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);
        app(OrderService::class)->setStatus($order, OrderStatus::Paid);
        try {
            app(OrderService::class)->setStatus($order, OrderStatus::Paid); // again: rejected by state machine
        } catch (ValidationException) {
        }

        $this->assertSame(2, Commission::where('order_id', $order->id)->count()); // direct + sponsor only
    }

    public function test_admin_can_record_manual_adjustment(): void
    {
        [, $child] = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->postJson(route('commissions.adjust'), [
            'partner_id' => $child->id, 'amount' => -16, 'note' => 'Reversal: order cancelled',
        ])->assertCreated();

        $this->assertDatabaseHas('commissions', ['partner_id' => $child->id, 'type' => 'adjustment', 'amount' => -16.00]);
    }

    public function test_partner_only_sees_own_commissions(): void
    {
        [$sponsor, $child, $customer, $product] = $this->scenario();
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);
        app(OrderService::class)->setStatus($order, OrderStatus::Paid);

        // child sees only their direct commission (1), not the sponsor's bonus row
        $this->actingAs($child->user)->getJson(route('commissions.index'))
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
