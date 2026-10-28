<?php

declare(strict_types=1);

use App\Enums\MemberStatus;

it('shows only prospects and active members in the default directory', function (): void {
    expect(MemberStatus::inDefaultDirectory())->toBe([MemberStatus::Prospect, MemberStatus::Active]);
});

it('says which statuses take a member out of active church life', function (MemberStatus $status, bool $leaves): void {
    expect($status->leavesActiveLife())->toBe($leaves);
})->with([
    [MemberStatus::Prospect, false],
    [MemberStatus::Active, false],
    [MemberStatus::Inactive, true],
    [MemberStatus::Transferred, true],
    [MemberStatus::Deceased, true],
    [MemberStatus::Archived, true],
]);

it('has a label for every status', function (): void {
    foreach (MemberStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
    }
});
