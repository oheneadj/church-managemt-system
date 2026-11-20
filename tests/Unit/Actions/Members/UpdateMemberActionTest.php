<?php

declare(strict_types=1);

use App\Actions\Members\UpdateMemberAction;
use App\DTOs\Members\UpdateMemberDTO;
use App\Enums\FamilyRole;
use App\Enums\MemberStatus;
use App\Events\MemberUpdated;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberDepartmentService;
use App\Services\MemberFamilyService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([MemberUpdated::class]);
    withoutDatabaseTransactions();
    $this->actor = unitUser();
    $this->members = Mockery::mock(MemberRepositoryInterface::class);
    $this->families = Mockery::mock(MemberFamilyService::class);
    $this->departments = Mockery::mock(MemberDepartmentService::class);
    $this->families->shouldReceive('guard');
    $this->families->shouldReceive('claimHead');
    $this->members->shouldReceive('save')->andReturnUsing(fn (Member $m) => $m);
    $this->members->shouldReceive('loadDetail')->andReturnUsing(fn (Member $m) => $m);
    $this->action = new UpdateMemberAction($this->members, $this->families, $this->departments);
});

it('changes only the fields sent and fires MemberUpdated with the old and new values', function (): void {
    $member = unitMember(['occupation' => 'Teacher', 'tribe' => 'Akan'], persisted: true);

    $result = $this->action->execute(new UpdateMemberDTO($this->actor, $member, ['occupation' => 'Nurse']));

    expect($result->occupation)->toBe('Nurse')->and($result->tribe)->toBe('Akan');
    Event::assertDispatched(MemberUpdated::class, fn (MemberUpdated $e) => $e->old === ['occupation' => 'Teacher'] && $e->new === ['occupation' => 'Nurse'] && $e->actor === $this->actor);
});

it('fires nothing when nothing changed', function (): void {
    $member = unitMember(['occupation' => 'Teacher'], persisted: true);

    $this->action->execute(new UpdateMemberDTO($this->actor, $member, ['occupation' => 'Teacher']));

    Event::assertNotDispatched(MemberUpdated::class);
});

it('leaves departments alone unless they were sent', function (): void {
    $this->departments->shouldReceive('sync')->never();

    $this->action->execute(new UpdateMemberDTO($this->actor, unitMember(persisted: true), ['occupation' => 'Nurse']));
});

it('replaces departments when they were sent, and records them in the change', function (): void {
    $member = unitMember(persisted: true);
    $this->departments->shouldReceive('sync')->once()->with($member, ['01choir']);

    $this->action->execute(new UpdateMemberDTO($this->actor, $member, [], ['01choir']));

    Event::assertDispatched(MemberUpdated::class, fn (MemberUpdated $e) => $e->new === ['department_ids' => ['01choir']]);
});

it('drops the family role when the family is removed', function (): void {
    $member = unitMember(['family_id' => '01family', 'family_role' => FamilyRole::Child], persisted: true);

    $result = $this->action->execute(new UpdateMemberDTO($this->actor, $member, ['family_id' => null]));

    expect($result->family_id)->toBeNull()->and($result->family_role)->toBeNull();
});

it('does not change the status', function (): void {
    $member = unitMember(persisted: true);

    $this->action->execute(new UpdateMemberDTO($this->actor, $member, ['occupation' => 'Nurse']));

    expect($member->status)->toBe(MemberStatus::Active);
});
