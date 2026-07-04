<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(RoleName::Admin->value);

        return $u;
    }

    public function test_admin_can_create_brand_and_category(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.brands.store'), ['name' => 'Acme', 'is_active' => 1])
            ->assertCreated();
        $this->assertDatabaseHas('brands', ['name' => 'Acme']);

        $this->actingAs($admin)->postJson(route('admin.categories.store'), ['name' => 'Gadgets', 'is_active' => 1])
            ->assertCreated();
        $this->assertDatabaseHas('categories', ['name' => 'Gadgets']);
    }

    public function test_admin_can_create_product(): void
    {
        $admin = $this->admin();
        $cat = Category::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($admin)->postJson(route('admin.products.store'), [
            'name' => 'Widget', 'sku' => 'WID-1', 'category_id' => $cat->id, 'brand_id' => $brand->id,
            'cost_price' => 10, 'retail_price' => 25, 'stock' => 100, 'is_active' => 1,
        ])->assertCreated()->assertJsonPath('data.name', 'Widget');

        $this->assertDatabaseHas('products', ['sku' => 'WID-1', 'retail_price' => 25.00]);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->actingAs($admin)->postJson(route('admin.products.store'), [
            'name' => 'X', 'sku' => 'DUP-1', 'cost_price' => 1, 'retail_price' => 2, 'stock' => 1,
        ])->assertStatus(422)->assertJsonValidationErrorFor('sku');
    }

    public function test_customer_cannot_manage_products(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cust = User::factory()->create();
        $cust->assignRole(RoleName::Customer->value);

        $this->actingAs($cust)->postJson(route('admin.products.store'), [
            'name' => 'X', 'sku' => 'Y', 'cost_price' => 1, 'retail_price' => 2, 'stock' => 1,
        ])->assertForbidden();
    }
}
