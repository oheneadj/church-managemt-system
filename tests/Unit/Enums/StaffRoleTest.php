<?php

declare(strict_types=1);

use App\Enums\StaffPermission;
use App\Enums\StaffRole;

it('gives the church admin every permission', function (): void {
    expect(StaffRole::ChurchAdmin->permissions())->toBe(StaffPermission::cases());
});

it('lets the pastor add and edit members but not deactivate or export them', function (): void {
    $pastor = StaffRole::Pastor->permissions();

    expect($pastor)->toContain(StaffPermission::MembersEdit)
        ->not->toContain(StaffPermission::MembersDeactivate)
        ->not->toContain(StaffPermission::MembersExport);
});

it('keeps milestones from the finance secretary and the department head', function (StaffRole $role): void {
    expect($role->permissions())->not->toContain(StaffPermission::MilestonesView);
})->with([StaffRole::FinanceSecretary, StaffRole::DepartmentHead]);

it('gives the department head and youth pastor read access only', function (StaffRole $role): void {
    expect($role->permissions())->toContain(StaffPermission::MembersView)->not->toContain(StaffPermission::MembersEdit);
})->with([StaffRole::DepartmentHead, StaffRole::YouthPastor]);

it('has a label for every role and permission', function (): void {
    foreach ([...StaffRole::cases(), ...StaffPermission::cases()] as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('uses the names stored in the database as values', function (): void {
    expect(StaffRole::FinanceSecretary->value)->toBe('finance-secretary')->and(StaffPermission::MembersEdit->value)->toBe('members.edit');
});
