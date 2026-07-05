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

class TreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tree(): array
    {
        $svc = app(PartnerService::class);
        $lvl = PartnerLevel::factory()->create();
        $a = $svc->register(['name' => 'A', 'email' => 'a@x.test', 'password' => 'password123', 'partner_level_id' => $lvl->id]);
        $b = $svc->register(['name' => 'B', 'email' => 'b@x.test', 'password' => 'password123', 'partner_level_id' => $lvl->id, 'sponsor_id' => $a->id]);
        Customer::factory()->create(['partner_id' => $b->id]);

        return [$a->fresh(), $b->fresh()];
    }

    public function test_admin_sees_roots_and_children(): void
    {
        [$a, $b] = $this->tree();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->getJson(route('tree.nodes'))
            ->assertOk()->assertJsonFragment(['id' => $a->id, 'type' => 'partner']);

        $this->actingAs($admin)->getJson(route('tree.nodes', ['parent' => $a->id]))
            ->assertOk()->assertJsonFragment(['id' => $b->id, 'type' => 'partner']);
    }

    public function test_children_include_customers(): void
    {
        [, $b] = $this->tree();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->getJson(route('tree.nodes', ['parent' => $b->id]))
            ->assertOk()->assertJsonFragment(['type' => 'customer']);
    }

    public function test_partner_root_is_self_and_can_expand_descendant(): void
    {
        [$a, $b] = $this->tree();

        $this->actingAs($a->user)->getJson(route('tree.nodes'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);

        $this->actingAs($a->user)->getJson(route('tree.nodes', ['parent' => $b->id]))->assertOk();
    }

    public function test_partner_cannot_expand_outside_subtree(): void
    {
        [$a, $b] = $this->tree();

        // B trying to inspect A (its ancestor) -> forbidden
        $this->actingAs($b->user)->getJson(route('tree.nodes', ['parent' => $a->id]))->assertForbidden();
    }
}
