<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(RoleName::Admin->value);

        return $u;
    }

    public function test_admin_can_update_settings_and_cache_reflects(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(route('admin.settings.update'), [
            'company_name' => 'Acme MLM', 'default_commission_percent' => 12,
        ])->assertOk();

        $this->assertDatabaseHas('settings', ['key' => 'company_name', 'value' => 'Acme MLM']);
        $this->assertSame('Acme MLM', app(SettingService::class)->get('company_name'));
    }

    public function test_mail_password_is_stored_encrypted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(route('admin.settings.update'), ['mail_password' => 'super-secret-key'])->assertOk();

        $row = Setting::where('key', 'mail_password')->first();
        $this->assertNotNull($row);
        $this->assertNotSame('super-secret-key', $row->value);
        $this->assertTrue((bool) $row->is_encrypted);
    }

    public function test_test_email_endpoint_succeeds(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.settings.test-email'))->assertOk();
    }

    public function test_customer_cannot_access_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cust = User::factory()->create();
        $cust->assignRole(RoleName::Customer->value);

        $this->actingAs($cust)->get(route('admin.settings.index'))->assertForbidden();
    }
}
