<?php

declare(strict_types=1);

use App\Actions\Members\ShowMemberAction;
use App\DTOs\Members\ShowMemberDTO;
use App\Repositories\MemberRepositoryInterface;

it('loads milestones only for a viewer who may see them', function (bool $canSeeMilestones): void {
    $member = unitMember();
    $members = Mockery::mock(MemberRepositoryInterface::class);
    $members->shouldReceive('loadDetail')->once()->with($member, $canSeeMilestones)->andReturn($member);

    $result = (new ShowMemberAction($members))->execute(new ShowMemberDTO(unitUser($canSeeMilestones), $member));

    expect($result)->toBe($member);
})->with([true, false]);
