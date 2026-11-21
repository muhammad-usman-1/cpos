<?php

use App\Enums\Permission as PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Managers now log in, so their role is narrowed to shop-floor work: sales, customers,
 * stitching orders and tailors. The owner can still widen it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        $manager = Role::query()->where('slug', Role::MANAGER)->first();

        if (! $manager) {
            return;
        }

        $names = array_map(fn (PermissionName $permission) => $permission->value, PermissionName::defaultsByRole()[Role::MANAGER]);

        $manager->permissions()->sync(Permission::query()->whereIn('name', $names)->pluck('id'));
        $manager->update(['description' => 'Sales, customers, stitching orders and tailors. Cannot buy stock, see cost or change settings']);
    }

    public function down(): void
    {
        // The earlier, wider permission set is not restored automatically.
    }
};
