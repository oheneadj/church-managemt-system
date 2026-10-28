<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the five staff roles and the permissions they carry (docs/06-architecture.md), straight from the
 * StaffRole and StaffPermission enums. Safe to run repeatedly: it only adds what is missing and re-syncs each role.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Creates every permission, then gives each role the permissions its enum case lists.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StaffPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (StaffRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions(array_map(fn (StaffPermission $p) => $p->value, $role->permissions()));
        }
    }
}
