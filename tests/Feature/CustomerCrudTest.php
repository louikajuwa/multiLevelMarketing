<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\User;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
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

    private function partner(float $discount = 10): Partner
    {
        $level = PartnerLevel::factory()->create(['discount_percent' => $discount]);

        return app(PartnerService::class)->register([
            'name' => 'P'.uniqid(), 'email' => uniqid().'@x.test', 'password' => 'password123',
            'partner_level_id' => $level->id,
        ]);
    }

    public function test_admin_can_create_customer_under_partner(): void
    {
        $admin = $this->admin();
        $partner = $this->partner(10);

        $this->actingAs($admin)->postJson(route('customers.store'), [
            'name' => 'Cust One', 'email' => 'c1@x.test', 'password' => 'password123',
            'partner_id' => $partner->id, 'discount_percent' => 8,
        ])->assertCreated()->assertJsonPath('data.discount_percent', '8.00');

        $this->assertDatabaseHas('customers', ['partner_id' => $partner->id]);
    }

    public function test_customer_discount_over_partner_cap_is_rejected(): void
    {
        $admin = $this->admin();
        $partner = $this->partner(10); // effective discount 10%

        $this->actingAs($admin)->postJson(route('customers.store'), [
            'name' => 'Cust Two', 'email' => 'c2@x.test', 'password' => 'password123',
            'partner_id' => $partner->id, 'discount_percent' => 15, // > 10
        ])->assertStatus(422)->assertJsonValidationErrorFor('discount_percent');
    }

    public function test_partner_only_sees_own_customers(): void
    {
        $p1 = $this->partner(10);
        $p2 = $this->partner(10);
        Customer::factory()->create(['partner_id' => $p1->id]);
        Customer::factory()->create(['partner_id' => $p2->id]);

        $this->actingAs($p1->user)
            ->getJson(route('customers.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_partner_registering_customer_is_forced_under_self(): void
    {
        $p1 = $this->partner(20);

        $this->actingAs($p1->user)->postJson(route('customers.store'), [
            'name' => 'Mine', 'email' => 'mine@x.test', 'password' => 'password123',
            'partner_id' => 99999, // attempt to assign elsewhere — should be overridden
            'discount_percent' => 5,
        ])->assertCreated();

        $this->assertDatabaseHas('customers', ['partner_id' => $p1->id]);
        $this->assertDatabaseMissing('customers', ['partner_id' => 99999]);
    }

    public function test_partner_cannot_delete_another_partners_customer(): void
    {
        $p1 = $this->partner(10);
        $p2 = $this->partner(10);
        $other = Customer::factory()->create(['partner_id' => $p2->id]);

        $this->actingAs($p1->user)
            ->deleteJson(route('customers.destroy', $other))
            ->assertForbidden();
    }
}
