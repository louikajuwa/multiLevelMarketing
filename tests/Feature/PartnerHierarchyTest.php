<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\User;
use App\Services\PartnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PartnerHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): PartnerService
    {
        return app(PartnerService::class);
    }

    private function level(): PartnerLevel
    {
        return PartnerLevel::factory()->create();
    }

    public function test_root_partner_has_root_path_and_depth_one(): void
    {
        $level = $this->level();
        $p = $this->service()->register([
            'name' => 'Root', 'email' => 'root@x.test', 'password' => 'password123',
            'partner_level_id' => $level->id,
        ]);

        $this->assertSame('/', $p->path);
        $this->assertSame(1, $p->depth);
        $this->assertNull($p->sponsor_id);
        $this->assertTrue($p->user->hasRole(RoleName::Partner->value));
    }

    public function test_child_path_depth_and_counts(): void
    {
        $level = $this->level();
        $svc = $this->service();

        $root = $svc->register(['name' => 'R', 'email' => 'r@x.test', 'password' => 'password123', 'partner_level_id' => $level->id]);
        $child = $svc->register(['name' => 'C', 'email' => 'c@x.test', 'password' => 'password123', 'partner_level_id' => $level->id, 'sponsor_id' => $root->id]);

        $this->assertSame('/'.$root->id.'/', $child->path);
        $this->assertSame(2, $child->depth);
        $this->assertSame($root->id, $child->sponsor_id);

        $root->refresh();
        $this->assertSame(1, $root->direct_referral_count);
        $this->assertSame(1, $root->total_downline_count);
    }

    public function test_grandchild_increments_all_ancestors(): void
    {
        $level = $this->level();
        $svc = $this->service();

        $root = $svc->register(['name' => 'R', 'email' => 'r2@x.test', 'password' => 'password123', 'partner_level_id' => $level->id]);
        $child = $svc->register(['name' => 'C', 'email' => 'c2@x.test', 'password' => 'password123', 'partner_level_id' => $level->id, 'sponsor_id' => $root->id]);
        $svc->register(['name' => 'G', 'email' => 'g2@x.test', 'password' => 'password123', 'partner_level_id' => $level->id, 'sponsor_id' => $child->id]);

        $this->assertSame(2, $root->fresh()->total_downline_count); // child + grandchild
        $this->assertSame(1, $root->fresh()->direct_referral_count); // only child directly
        $this->assertSame(1, $child->fresh()->total_downline_count); // grandchild
    }

    public function test_max_depth_is_enforced(): void
    {
        $level = $this->level();
        $svc = $this->service();

        $p = $svc->register(['name' => 'L1', 'email' => 'l1@x.test', 'password' => 'password123', 'partner_level_id' => $level->id]);
        foreach (['l2', 'l3', 'l4'] as $i => $email) {
            $p = $svc->register(['name' => strtoupper($email), 'email' => "$email@x.test", 'password' => 'password123', 'partner_level_id' => $level->id, 'sponsor_id' => $p->id]);
        }
        // p is now at depth 4; a child would be depth 5 > max 4.
        $this->expectException(ValidationException::class);
        $svc->register(['name' => 'L5', 'email' => 'l5@x.test', 'password' => 'password123', 'partner_level_id' => $level->id, 'sponsor_id' => $p->id]);
    }

    public function test_admin_can_register_partner_via_http(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $level = $this->level();

        $this->actingAs($admin)->postJson(route('admin.partners.store'), [
            'name' => 'New Partner', 'email' => 'np@x.test', 'password' => 'password123',
            'partner_level_id' => $level->id,
        ])->assertCreated()->assertJsonPath('data.depth', 1);

        $this->assertDatabaseHas('users', ['email' => 'np@x.test']);
        $this->assertSame(1, Partner::count());
    }

    public function test_effective_discount_prefers_override(): void
    {
        $level = PartnerLevel::factory()->create(['discount_percent' => 10]);
        $svc = $this->service();
        $p = $svc->register(['name' => 'D', 'email' => 'd@x.test', 'password' => 'password123', 'partner_level_id' => $level->id, 'discount_override_percent' => 22.5]);

        $this->assertSame(22.5, $svc->effectiveDiscountPercent($p->fresh()->load('level')));
    }
}
