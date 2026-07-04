<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_do_anything_via_gate(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        $this->assertTrue($user->can('roles.delete'));
        $this->assertTrue($user->can('anything.not.even.defined'));
    }

    public function test_admin_has_management_but_not_role_deletion(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        $this->assertTrue($user->can('orders.view'));
        $this->assertTrue($user->can('partners.create'));
        $this->assertFalse($user->can('roles.delete'));
    }

    public function test_partner_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Partner->value);

        $this->assertTrue($user->can('customers.create'));
        $this->assertTrue($user->can('commissions.view'));
        $this->assertFalse($user->can('products.create'));
        $this->assertFalse($user->can('commissions.adjust'));
    }

    public function test_customer_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Customer->value);

        $this->assertTrue($user->can('orders.create'));
        $this->assertTrue($user->can('products.view'));
        $this->assertFalse($user->can('customers.view'));
    }
}
