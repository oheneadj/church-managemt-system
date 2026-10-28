<?php

declare(strict_types=1);

use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Enums\MilestoneType;
use App\Enums\StaffPermission;

// The React app mirrors some enums in resources/js/constants/enums.ts and permissions.ts (agreement section 13:
// no magic strings on either side). These tests fail if a list in TypeScript drifts from its PHP enum.

/** Reads one `export const NAME = [ { value: 'x', label: 'Y' }, ... ]` list out of the TypeScript file. */
function frontendOptions(string $constant): array
{
    $source = (string) file_get_contents(base_path('resources/js/constants/enums.ts'));
    preg_match('/export const '.$constant.' = \[(.*?)\] as const/s', $source, $block);
    preg_match_all("/value: '([^']+)', label: '([^']+)'/", $block[1] ?? '', $pairs, PREG_SET_ORDER);

    return array_column($pairs, 2, 1);
}

it('mirrors each enum exactly, values and labels', function (string $constant, string $enum): void {
    $expected = [];
    foreach ($enum::cases() as $case) {
        $expected[$case->value] = $case->label();
    }

    expect(frontendOptions($constant))->toBe($expected);
})->with([
    ['GENDERS', Gender::class],
    ['MARITAL_STATUSES', MaritalStatus::class],
    ['MEMBERSHIP_TYPES', MembershipType::class],
    ['MEMBER_STATUSES', MemberStatus::class],
    ['FAMILY_ROLES', FamilyRole::class],
    ['MILESTONE_TYPES', MilestoneType::class],
    ['EDUCATIONAL_LEVELS', EducationalLevel::class],
]);

it('only names permissions that exist', function (): void {
    $source = (string) file_get_contents(base_path('resources/js/constants/permissions.ts'));
    preg_match_all("/: '([a-z-]+\.[a-z-]+)'/", $source, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $permission) {
        expect(StaffPermission::tryFrom($permission))->not->toBeNull();
    }
});
