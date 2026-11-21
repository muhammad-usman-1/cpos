<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Safe to re-run: new permissions are added and granted to roles by default, without touching
 * permissions the owner has already changed.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $newPermissions = [];

        foreach (PermissionName::cases() as $permission) {
            $record = Permission::query()->updateOrCreate(
                ['name' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            );

            if ($record->wasRecentlyCreated) {
                $newPermissions[] = $permission->value;
            }
        }

        $roles = [
            Role::OWNER => ['Owner / Admin', 'Everything, including cost prices, users and settings'],
            'manager' => ['Manager', 'Sales, customers, stitching orders and tailors. Cannot buy stock, see cost or change settings'],
            'salesman' => ['Salesman / Cashier', 'Counter work: sales, customers, measurements and stitching orders'],
            'tailor' => ['Tailor / Workshop', 'Assigned stitching orders only'],
        ];

        foreach ($roles as $slug => [$name, $description]) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'is_system' => true,
            ]);

            $defaults = array_map(fn (PermissionName $permission) => $permission->value, PermissionName::defaultsByRole()[$slug] ?? []);
            $grant = $role->wasRecentlyCreated ? $defaults : array_intersect($defaults, $newPermissions);

            if ($grant !== []) {
                $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', $grant)->pluck('id'));
            }
        }
    }
}
