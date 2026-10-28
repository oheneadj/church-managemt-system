<?php

declare(strict_types=1);

use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('lets the church admin and the pastor add and edit members', function (string $role): void {
    expect(Role::findByName($role, 'web')->hasPermissionTo('members.edit'))->toBeTrue();
})->with(['church-admin', 'pastor']);

it('keeps read-only roles from editing members', function (string $role): void {
    expect(Role::findByName($role, 'web')->hasPermissionTo('members.edit'))->toBeFalse();
})->with(['finance-secretary', 'department-head', 'youth-pastor']);

it('keeps exporting and staff management with the church admin', function (): void {
    expect(Role::findByName('pastor', 'web')->hasPermissionTo('members.export'))->toBeFalse()
        ->and(Role::findByName('church-admin', 'web')->hasPermissionTo('members.export'))->toBeTrue();
});
