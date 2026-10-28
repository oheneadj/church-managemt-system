<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;

it('only returns rows of the current church', function (): void {
    $churchA = Church::factory()->create();
    $churchB = Church::factory()->create();
    $memberA = Member::factory()->for($churchA)->create();
    Member::factory()->for($churchB)->create();

    app()->instance('current.church', $churchA);

    expect(Member::query()->pluck('id')->all())->toBe([$memberA->id]);
});

it('cannot find another church\'s row by id', function (): void {
    $churchA = Church::factory()->create();
    $churchB = Church::factory()->create();
    $memberB = Member::factory()->for($churchB)->create();

    app()->instance('current.church', $churchA);

    expect(Member::query()->find($memberB->id))->toBeNull();
});

it('stamps church_id on new rows from the current church', function (): void {
    $church = Church::factory()->create();
    $user = User::factory()->for($church)->create();
    app()->instance('current.church', $church);

    $member = Member::query()->create([
        'member_number' => 'GRC-0001',
        'first_name' => 'Abena',
        'last_name' => 'Asante',
        'gender' => Gender::Female,
        'phone' => '+233200000009',
        'created_by' => $user->id,
    ]);

    expect($member->church_id)->toBe($church->id);
});

it('scopes aggregates to the current church', function (): void {
    $churchA = Church::factory()->create();
    $churchB = Church::factory()->create();
    Member::factory()->count(2)->for($churchA)->create();
    Member::factory()->count(5)->for($churchB)->create();

    app()->instance('current.church', $churchA);

    expect(Member::query()->count())->toBe(2);
});
