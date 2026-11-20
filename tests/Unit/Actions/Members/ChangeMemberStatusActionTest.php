<?php

declare(strict_types=1);

use App\Actions\Members\ChangeMemberStatusAction;
use App\DTOs\Members\ChangeMemberStatusDTO;
use App\Enums\MemberStatus;
use App\Events\MemberStatusChanged;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Support\Facades\Event;

it('moves the member, keeps the note and fires MemberStatusChanged with the previous status', function (): void {
    Event::fake([MemberStatusChanged::class]);
    withoutDatabaseTransactions();
    $actor = unitUser();
    $member = unitMember(persisted: true);
    $members = Mockery::mock(MemberRepositoryInterface::class);
    $members->shouldReceive('save')->once()->andReturnUsing(fn (Member $m) => $m);
    $members->shouldReceive('loadDetail')->once()->andReturnUsing(fn (Member $m) => $m);

    $result = (new ChangeMemberStatusAction($members))->execute(new ChangeMemberStatusDTO($actor, $member, MemberStatus::Inactive, 'Moved to Kumasi'));

    expect($result->status)->toBe(MemberStatus::Inactive)
        ->and($result->status_note)->toBe('Moved to Kumasi')
        ->and($result->status_changed_at)->not->toBeNull();
    Event::assertDispatched(MemberStatusChanged::class, fn (MemberStatusChanged $e) => $e->previous === MemberStatus::Active && $e->note === 'Moved to Kumasi' && $e->actor === $actor);
});
