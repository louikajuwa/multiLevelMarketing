<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\PartnerLevel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLevelCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(RoleName::Admin->value);

        return $u;
    }

    public function test_index_page_renders(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.partner-levels.index'))
            ->assertOk()
            ->assertSee('Partner Levels');
    }

    public function test_admin_can_list_partner_levels(): void
    {
        $admin = $this->admin();
        PartnerLevel::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson(route('admin.partner-levels.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_partner_level(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.partner-levels.store'), [
            'name' => 'Gold', 'level' => 5, 'discount_percent' => 25,
            'commission_percent' => 12, 'sponsor_bonus_percent' => 4, 'is_active' => 1,
        ])->assertCreated()->assertJsonPath('data.name', 'Gold');

        $this->assertDatabaseHas('partner_levels', ['level' => 5, 'discount_percent' => 25.00]);
    }

    public function test_duplicate_level_is_rejected(): void
    {
        $admin = $this->admin();
        PartnerLevel::factory()->create(['level' => 2]);

        $this->actingAs($admin)->postJson(route('admin.partner-levels.store'), [
            'name' => 'Dup', 'level' => 2, 'discount_percent' => 10,
        ])->assertStatus(422)->assertJsonValidationErrorFor('level');
    }

    public function test_admin_can_update_and_delete(): void
    {
        $admin = $this->admin();
        $level = PartnerLevel::factory()->create(['level' => 7, 'name' => 'Old']);

        $this->actingAs($admin)->putJson(route('admin.partner-levels.update', $level), [
            'name' => 'New', 'level' => 7, 'discount_percent' => 30,
        ])->assertOk();
        $this->assertDatabaseHas('partner_levels', ['id' => $level->id, 'name' => 'New']);

        $this->actingAs($admin)->deleteJson(route('admin.partner-levels.destroy', $level))->assertOk();
        $this->assertDatabaseMissing('partner_levels', ['id' => $level->id]);
    }

    public function test_partner_cannot_access_partner_levels(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $partner = User::factory()->create();
        $partner->assignRole(RoleName::Partner->value);

        $this->actingAs($partner)
            ->getJson(route('admin.partner-levels.index'))
            ->assertForbidden();
    }
}
