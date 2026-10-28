<?php

declare(strict_types=1);

use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MemberStatus;

it('casts its fixed-value columns to enums', function (): void {
    $member = unitMember(['gender' => 'female', 'status' => 'inactive', 'family_role' => 'head']);

    expect($member->gender)->toBe(Gender::Female)
        ->and($member->status)->toBe(MemberStatus::Inactive)
        ->and($member->family_role)->toBe(FamilyRole::Head);
});

it('only lets the listed columns be mass assigned', function (): void {
    $member = unitMember();
    $member->fill(['first_name' => 'Abena', 'id' => 'chosen-by-client', 'deleted_at' => now()]);

    expect($member->first_name)->toBe('Abena')->and($member->id)->not->toBe('chosen-by-client')->and($member->deleted_at)->toBeNull();
});

it('keeps its encrypted columns out of plain storage', function (): void {
    expect(unitMember()->getCasts())->toMatchArray(['address' => 'encrypted', 'national_id_number' => 'encrypted']);
});

it('writes the full name with single spaces, with or without a middle name', function (?string $middle, string $expected): void {
    $member = unitMember(['first_name' => 'Emmanuel', 'middle_name' => $middle, 'last_name' => 'Asante']);

    expect($member->full_name)->toBe($expected);
})->with([
    'no middle name' => [null, 'Emmanuel Asante'],
    'an empty middle name' => ['', 'Emmanuel Asante'],
    'a middle name' => ['Kwame', 'Emmanuel Kwame Asante'],
]);
