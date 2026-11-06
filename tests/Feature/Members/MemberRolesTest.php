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

it('gives the member list export to the church admin and the finance secretary only', function (): void {
    expect(Role::findByName('church-admin', 'web')->hasPermissionTo('members.export'))->toBeTrue()
        ->and(Role::findByName('finance-secretary', 'web')->hasPermissionTo('members.export'))->toBeTrue()
        ->and(Role::findByName('pastor', 'web')->hasPermissionTo('members.export'))->toBeFalse();
});

it('keeps the one-member personal data export with the church admin (D-055)', function (string $role, bool $allowed): void {
    expect(Role::findByName($role, 'web')->hasPermissionTo('members.data-export'))->toBe($allowed);
})->with([['church-admin', true], ['pastor', false], ['finance-secretary', false], ['department-head', false], ['youth-pastor', false]]);
