<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PartnerService;
use App\Services\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(RoleName::Admin->value);

        return $u;
    }

    private function partner(?Partner $sponsor = null, float $discount = 10): Partner
    {
        $level = PartnerLevel::factory()->create(['discount_percent' => $discount]);

        return app(PartnerService::class)->register([
            'name' => 'P'.uniqid(), 'email' => uniqid().'@x.test', 'password' => 'password123',
            'partner_level_id' => $level->id, 'sponsor_id' => $sponsor?->id,
        ]);
    }

    private function orderFor(Customer $customer): Order
    {
        $product = Product::factory()->create(['retail_price' => 100, 'stock' => 10]);

        return app(OrderService::class)->place($customer, [['product_id' => $product->id, 'quantity' => 1]]);
    }

    // --- option endpoints -------------------------------------------------

    public function test_partner_option_endpoint_is_scoped_to_own_downline(): void
    {
        $root = $this->partner();
        $child = $this->partner($root);
        $other = $this->partner();

        $ids = collect($this->actingAs($root->user)->getJson(route('customers.partner-options'))
            ->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$root->id, $child->id], $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_admin_option_endpoints_see_all_partners(): void
    {
        $a = $this->partner();
        $b = $this->partner();
        $admin = $this->admin();

        foreach (['customers.partner-options', 'admin.partners.sponsor-options', 'commissions.partner-options'] as $route) {
            $ids = collect($this->actingAs($admin)->getJson(route($route))->assertOk()->json('data'))->pluck('id')->all();
            $this->assertContains($a->id, $ids, $route);
            $this->assertContains($b->id, $ids, $route);
        }
    }

    public function test_partner_list_is_scoped_for_non_staff(): void
    {
        $root = $this->partner();
        $child = $this->partner($root);
        $other = $this->partner();

        $ids = collect($this->actingAs($root->user)->getJson(route('admin.partners.index'))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$root->id, $child->id], $ids);
        $this->assertNotContains($other->id, $ids);
    }

    // --- commission adjustment -------------------------------------------

    public function test_adjustment_with_foreign_order_is_rejected(): void
    {
        $p1 = $this->partner();
        $p2 = $this->partner();
        $order = $this->orderFor(Customer::factory()->create(['partner_id' => $p2->id]));

        $this->actingAs($this->admin())->postJson(route('commissions.adjust'), [
            'partner_id' => $p1->id, 'amount' => 10, 'note' => 'x', 'order_id' => $order->id,
        ])->assertStatus(422)->assertJsonValidationErrorFor('order_id');
    }

    public function test_adjustment_with_own_order_is_accepted(): void
    {
        $p = $this->partner();
        $order = $this->orderFor(Customer::factory()->create(['partner_id' => $p->id]));

        $this->actingAs($this->admin())->postJson(route('commissions.adjust'), [
            'partner_id' => $p->id, 'amount' => 10, 'note' => 'ok', 'order_id' => $order->id,
        ])->assertCreated();
    }

    public function test_zero_and_huge_adjustment_amounts_are_rejected(): void
    {
        $p = $this->partner();
        $admin = $this->admin();

        foreach ([0, 1000001, -1000001] as $amount) {
            $this->actingAs($admin)->postJson(route('commissions.adjust'), [
                'partner_id' => $p->id, 'amount' => $amount, 'note' => 'x',
            ])->assertStatus(422)->assertJsonValidationErrorFor('amount');
        }
    }

    public function test_adjustment_note_is_capped_at_500(): void
    {
        $p = $this->partner();

        $this->actingAs($this->admin())->postJson(route('commissions.adjust'), [
            'partner_id' => $p->id, 'amount' => 5, 'note' => str_repeat('a', 501),
        ])->assertStatus(422)->assertJsonValidationErrorFor('note');
    }

    // --- category cycles --------------------------------------------------

    public function test_category_cannot_be_its_own_parent_or_descendant_of_itself(): void
    {
        $admin = $this->admin();
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);
        $c = Category::factory()->create(['parent_id' => $b->id]);

        $this->actingAs($admin)->putJson(route('admin.categories.update', $a), ['name' => 'A', 'parent_id' => $a->id])
            ->assertStatus(422);
        $this->actingAs($admin)->putJson(route('admin.categories.update', $a), ['name' => 'A', 'parent_id' => $c->id])
            ->assertStatus(422)->assertJsonValidationErrorFor('parent_id');

        $this->assertNull($a->fresh()->parent_id);

        // A valid re-parent still works.
        $this->actingAs($admin)->putJson(route('admin.categories.update', $c), ['name' => 'C', 'parent_id' => $a->id])
            ->assertOk();
    }

    // --- partner deletion -------------------------------------------------

    public function test_partner_delete_decrements_ancestor_counters(): void
    {
        $root = $this->partner();
        $mid = $this->partner($root);
        $leaf = $this->partner($mid);

        $this->assertSame(2, $root->fresh()->total_downline_count);

        $this->actingAs($this->admin())->deleteJson(route('admin.partners.destroy', $leaf))->assertOk();

        $this->assertDatabaseMissing('partners', ['id' => $leaf->id]);
        $this->assertDatabaseMissing('users', ['id' => $leaf->user_id]);
        $this->assertSame(1, $root->fresh()->total_downline_count);
        $this->assertSame(0, $mid->fresh()->total_downline_count);
        $this->assertSame(0, $mid->fresh()->direct_referral_count);
        $this->assertSame(1, $root->fresh()->direct_referral_count);
    }

    public function test_partner_delete_blocked_with_children_or_customers(): void
    {
        $root = $this->partner();
        $this->partner($root);
        $solo = $this->partner();
        Customer::factory()->create(['partner_id' => $solo->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->deleteJson(route('admin.partners.destroy', $root))->assertStatus(422);
        $this->actingAs($admin)->deleteJson(route('admin.partners.destroy', $solo))->assertStatus(422);
        $this->assertDatabaseHas('partners', ['id' => $root->id]);
        $this->assertSame(1, $root->fresh()->total_downline_count);
    }

    // --- login / order status gates --------------------------------------

    public function test_suspended_partner_cannot_log_in(): void
    {
        $p = $this->partner();
        $p->update(['status' => 'suspended']);

        $this->postJson('/login', ['email' => $p->user->email, 'password' => 'password123'])
            ->assertStatus(422)->assertJsonValidationErrorFor('email');
        $this->assertGuest();

        $p->update(['status' => 'active']);
        $this->postJson('/login', ['email' => $p->user->email, 'password' => 'password123'])->assertOk();
    }

    public function test_inactive_customer_cannot_log_in(): void
    {
        $customer = Customer::factory()->create(['status' => 'inactive']);
        $customer->user->update(['password' => 'password123']);

        $this->postJson('/login', ['email' => $customer->user->email, 'password' => 'password123'])
            ->assertStatus(422);
        $this->assertGuest();
    }

    public function test_cannot_place_order_for_customer_of_inactive_partner_or_inactive_customer(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['retail_price' => 100, 'stock' => 10]);
        $payload = fn (Customer $c) => ['customer_id' => $c->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $partner = $this->partner();
        $customer = Customer::factory()->create(['partner_id' => $partner->id]);
        $this->actingAs($admin)->postJson(route('orders.store'), $payload($customer))->assertSuccessful();

        $partner->update(['status' => 'inactive']);
        $this->actingAs($admin)->postJson(route('orders.store'), $payload($customer))
            ->assertStatus(422)->assertJsonValidationErrorFor('customer_id');

        $partner->update(['status' => 'active']);
        $customer->update(['status' => 'inactive']);
        $this->actingAs($admin)->postJson(route('orders.store'), $payload($customer))
            ->assertStatus(422)->assertJsonValidationErrorFor('customer_id');
    }

    // --- realtime / throttling -------------------------------------------

    public function test_realtime_token_returns_503_when_secret_empty(): void
    {
        config(['realtime.secret' => '']);

        $this->actingAs($this->admin())->getJson(route('realtime.token'))
            ->assertStatus(503)->assertJsonPath('message', 'Realtime not configured');
    }

    public function test_forgot_password_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('password.email'), ['email' => 'nobody@x.test'])->assertStatus(422);
        }

        $this->postJson(route('password.email'), ['email' => 'nobody@x.test'])->assertStatus(429);
    }

    // --- settings cache ---------------------------------------------------

    public function test_encrypted_settings_are_not_cached_but_readable(): void
    {
        $settings = app(SettingService::class);
        $settings->set('mail_password', 'hunter2', true);

        $this->assertArrayNotHasKey('mail_password', $settings->all());
        $this->assertSame('hunter2', $settings->get('mail_password'));
    }
}
