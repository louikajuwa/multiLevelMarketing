<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\PartnerLevel;
use App\Models\User;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_dashboard_renders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Revenue')->assertSee('Partners');
    }

    public function test_partner_dashboard_renders(): void
    {
        $level = PartnerLevel::factory()->create();
        $partner = app(PartnerService::class)->register([
            'name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $level->id,
        ]);

        $this->actingAs($partner->user)->get('/dashboard')->assertOk()->assertSee('My earnings')->assertSee('Downline');
    }

    public function test_customer_dashboard_renders(): void
    {
        $level = PartnerLevel::factory()->create();
        $partner = app(PartnerService::class)->register([
            'name' => 'P', 'email' => 'p2@x.test', 'password' => 'password123', 'partner_level_id' => $level->id,
        ]);
        $customer = Customer::factory()->create(['partner_id' => $partner->id]);
        $customer->user->assignRole(RoleName::Customer->value);

        $this->actingAs($customer->user)->get('/dashboard')->assertOk()->assertSee('My discount');
    }
}
