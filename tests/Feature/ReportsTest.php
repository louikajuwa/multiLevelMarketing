<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(RoleName::Admin->value);

        return $u;
    }

    public function test_reports_index_renders(): void
    {
        $this->actingAs($this->admin())->get(route('reports.index'))->assertOk()->assertSee('Reports');
    }

    public function test_admin_can_export_excel(): void
    {
        Excel::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('reports.export', ['type' => 'inventory', 'format' => 'xlsx']))->assertOk();

        Excel::assertDownloaded('inventory-'.now()->format('Ymd').'.xlsx');
    }

    public function test_admin_can_export_csv(): void
    {
        Excel::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('reports.export', ['type' => 'partners', 'format' => 'csv']))->assertOk();

        Excel::assertDownloaded('partners-'.now()->format('Ymd').'.csv');
    }

    public function test_admin_can_export_pdf(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'sales', 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.export', ['type' => 'nope', 'format' => 'xlsx']))
            ->assertStatus(302); // validation redirect (non-ajax GET)
    }

    public function test_customer_cannot_access_reports(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cust = User::factory()->create();
        $cust->assignRole(RoleName::Customer->value);

        $this->actingAs($cust)->get(route('reports.index'))->assertForbidden();
    }
}
