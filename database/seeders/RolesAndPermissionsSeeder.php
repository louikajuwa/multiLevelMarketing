<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Modules and their CRUD-ish actions. Permission name = "module.action".
     *
     * @var array<string, string[]>
     */
    private array $modules = [
        'partners' => ['view', 'create', 'update', 'delete'],
        'partner_levels' => ['view', 'create', 'update', 'delete'],
        'customers' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'brands' => ['view', 'create', 'update', 'delete'],
        'orders' => ['view', 'create', 'update', 'delete', 'update_status'],
        'commissions' => ['view', 'adjust'],
        'reports' => ['view'],
        'settings' => ['view', 'update'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'audit' => ['view'],
        'tree' => ['view'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::findOrCreate($name, 'web');
                $all[] = $name;
            }
        }

        // Super Admin — everything (also covered by Gate::before, but assigned so
        // spatie permission-middleware passes too).
        $super = Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
        $super->syncPermissions($all);

        // Admin — full management except creating/deleting roles.
        $adminPerms = array_values(array_filter(
            $all,
            fn (string $p) => ! in_array($p, ['roles.create', 'roles.update', 'roles.delete'], true)
        ));
        Role::findOrCreate(RoleName::Admin->value, 'web')->syncPermissions($adminPerms);

        // Partner — manage own customers + place/view orders, see own tree/commissions.
        Role::findOrCreate(RoleName::Partner->value, 'web')->syncPermissions([
            'partners.view',
            'customers.view', 'customers.create', 'customers.update',
            'products.view',
            'orders.view', 'orders.create',
            'commissions.view',
            'reports.view',
            'tree.view',
        ]);

        // Customer — browse products, place + view own orders.
        Role::findOrCreate(RoleName::Customer->value, 'web')->syncPermissions([
            'products.view',
            'orders.view', 'orders.create',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
