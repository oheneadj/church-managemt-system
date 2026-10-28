<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the five staff roles and the permissions they carry (docs/06-architecture.md).
 * Safe to run repeatedly: it only adds what is missing and re-syncs each role.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'members.view', 'members.edit', 'members.deactivate', 'members.export', 'milestones.view',
        'giving.summary', 'giving.record', 'giving.edit', 'giving.export',
        'sms.send',
        'reports.view', 'reports.export',
        'church.settings', 'staff.manage', 'fund-types.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'church-admin' => self::PERMISSIONS,
            'pastor' => ['members.view', 'members.edit', 'milestones.view', 'giving.summary', 'reports.view', 'sms.send'],
            'finance-secretary' => ['members.view', 'giving.summary', 'giving.record', 'giving.edit', 'giving.export', 'reports.view', 'reports.export'],
            'department-head' => ['members.view'],
            'youth-pastor' => ['members.view', 'milestones.view'],
        ];

        foreach ($roles as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
