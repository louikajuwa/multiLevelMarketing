<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationEdgeTest extends TestCase
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

    public function test_nonexistent_customer_id_returns_404_on_put_and_delete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/customers/999999', ['status' => 'active'])->assertNotFound();
        $this->actingAs($admin)->deleteJson('/customers/999999')->assertNotFound();
    }

    public function test_weird_query_params_do_not_500_on_list_endpoints(): void
    {
        $admin = $this->admin();
        Customer::factory()->count(3)->create();

        $bad = [
            ['per_page' => 'abc'], ['per_page' => -5], ['per_page' => 0], ['per_page' => 100000],
            ['page' => 'abc'], ['page' => -1], ['page' => 0], ['page' => 99999],
            ['per_page' => ['1']], ['page' => ['1']], ['search' => ['x']], ['sort_by' => ['id']],
            ['sort_dir' => ['asc']], ['status' => ['active']],
        ];
        $routes = ['customers.index', 'admin.partners.index', 'orders.index', 'commissions.index',
            'admin.brands.index', 'admin.categories.index', 'admin.products.index',
            'orders.customer-options', 'orders.product-options', 'customers.partner-options',
            'commissions.partner-options', 'admin.partners.sponsor-options'];

        foreach ($routes as $route) {
            foreach ($bad as $params) {
                $res = $this->actingAs($admin)->getJson(route($route, $params));
                $this->assertLessThan(500, $res->status(), $route.' '.json_encode($params));
            }
        }
    }

    public function test_per_page_is_clamped_to_100(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('customers.index', ['per_page' => 100000]))
            ->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->actingAs($admin)->getJson(route('customers.index', ['per_page' => -3]))
            ->assertOk()->assertJsonPath('meta.per_page', 15);
    }
}
