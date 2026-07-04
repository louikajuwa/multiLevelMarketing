<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PartnerLevelSeeder::class,
            SettingsSeeder::class,
        ]);

        // Staff accounts
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@mlm.test'],
            ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true],
        );
        $superAdmin->syncRoles([RoleName::SuperAdmin->value]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@mlm.test'],
            ['name' => 'Admin User', 'password' => 'password', 'is_active' => true],
        );
        $admin->syncRoles([RoleName::Admin->value]);

        $this->call([
            DemoSeeder::class,
        ]);
    }
}
