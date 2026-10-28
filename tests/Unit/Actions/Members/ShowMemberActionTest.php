<?php

declare(strict_types=1);

use App\Actions\Members\ShowMemberAction;
use App\DTOs\Members\ShowMemberDTO;
use App\Events\MemberViewed;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake([MemberViewed::class]));

it('loads milestones only for a viewer who may see them', function (bool $canSeeMilestones): void {
    $member = unitMember();
    $members = Mockery::mock(MemberRepositoryInterface::class);
    $members->shouldReceive('loadDetail')->once()->with($member, $canSeeMilestones)->andReturn($member);

    $result = (new ShowMemberAction($members))->execute(new ShowMemberDTO(unitUser($canSeeMilestones), $member));

    expect($result)->toBe($member);
})->with([true, false]);

it('puts every look at the record on file by firing MemberViewed', function (): void {
    $member = unitMember();
    $viewer = unitUser();
    $members = Mockery::mock(MemberRepositoryInterface::class);
    $members->shouldReceive('loadDetail')->andReturn($member);

    (new ShowMemberAction($members))->execute(new ShowMemberDTO($viewer, $member));

    Event::assertDispatched(MemberViewed::class, fn (MemberViewed $e) => $e->member === $member && $e->viewer === $viewer);
});
