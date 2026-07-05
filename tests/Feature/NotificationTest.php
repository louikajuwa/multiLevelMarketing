<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\PartnerLevel;
use App\Models\Product;
use App\Models\User;
use App\Notifications\CommissionEarnedNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\NewPartnerNotification;
use App\Services\OrderService;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function level(): PartnerLevel
    {
        return PartnerLevel::factory()->create(['discount_percent' => 0, 'commission_percent' => 10, 'sponsor_bonus_percent' => 5]);
    }

    public function test_new_partner_notifies_admins(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        app(PartnerService::class)->register(['name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $this->level()->id]);

        Notification::assertSentTo($admin, NewPartnerNotification::class);
    }

    public function test_order_notifies_partner(): void
    {
        Notification::fake();
        $partner = app(PartnerService::class)->register(['name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $this->level()->id]);
        $customer = Customer::factory()->create(['partner_id' => $partner->id]);
        $product = Product::factory()->create(['retail_price' => 50, 'stock' => 5]);

        app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);

        Notification::assertSentTo($partner->user, NewOrderNotification::class);
    }

    public function test_paid_order_notifies_commission(): void
    {
        Notification::fake();
        $partner = app(PartnerService::class)->register(['name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $this->level()->id]);
        $customer = Customer::factory()->create(['partner_id' => $partner->id]);
        $product = Product::factory()->create(['retail_price' => 50, 'stock' => 5]);
        $order = app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);

        app(OrderService::class)->setStatus($order, OrderStatus::Paid);

        Notification::assertSentTo($partner->user, CommissionEarnedNotification::class);
    }

    public function test_bell_lists_and_marks_read(): void
    {
        $partner = app(PartnerService::class)->register(['name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $this->level()->id]);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $admin->notify(new NewPartnerNotification($partner));

        $this->actingAs($admin)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('data.unread', 1);

        $this->actingAs($admin)->postJson(route('notifications.read'))->assertOk();

        $this->actingAs($admin)->getJson(route('notifications.index'))->assertJsonPath('data.unread', 0);
    }
}
