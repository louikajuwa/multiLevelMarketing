<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\User;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_model_create_and_update_are_audited(): void
    {
        $level = PartnerLevel::factory()->create();
        $partner = app(PartnerService::class)->register([
            'name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $level->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'auditable_type' => Partner::class, 'auditable_id' => $partner->id]);

        $partner->update(['status' => 'suspended']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'auditable_type' => Partner::class, 'auditable_id' => $partner->id]);
    }

    public function test_login_is_recorded_in_activity(): void
    {
        $user = User::factory()->create(['email' => 'log@x.test', 'password' => 'password', 'is_active' => true]);

        $this->postJson('/login', ['email' => 'log@x.test', 'password' => 'password'])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'event' => 'login']);
    }

    public function test_admin_can_view_audit_and_customer_cannot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin)->getJson(route('admin.audit.index'))->assertOk()->assertJsonPath('success', true);

        $cust = User::factory()->create();
        $cust->assignRole(RoleName::Customer->value);
        $this->actingAs($cust)->get(route('admin.audit.index'))->assertForbidden();
    }
}
