<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamilyAction;
use App\DTOs\Families\CreateFamilyDTO;
use App\Enums\FamilyRole;
use App\Events\MemberUpdated;
use App\Exceptions\MemberAlreadyInFamilyException;
use App\Repositories\FamilyRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberFamilyService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([MemberUpdated::class]);
    withoutDatabaseTransactions();
    $this->actor = unitUser();
    $this->family = unitFamily();
    $this->families = Mockery::mock(FamilyRepositoryInterface::class);
    $this->members = Mockery::mock(MemberRepositoryInterface::class);
    $this->memberFamilies = Mockery::mock(MemberFamilyService::class);
    $this->action = new CreateFamilyAction($this->families, $this->members, $this->memberFamilies);
    $this->families->shouldReceive('create')->once()
        ->with(['church_id' => '01churchunit', 'name' => 'Boateng Family', 'notes' => null])->andReturn($this->family);
    $this->families->shouldReceive('loadWithVisibleMembers')->andReturn($this->family);
});

it('creates a family with no head', function (): void {
    $this->members->shouldReceive('findOrFail')->never();

    $result = $this->action->execute(new CreateFamilyDTO($this->actor, 'Boateng Family'));

    expect($result)->toBe($this->family);
    Event::assertNotDispatched(MemberUpdated::class);
});

it('makes the chosen member the head and fires MemberUpdated for them', function (): void {
    $head = unitMember(persisted: true);
    $this->members->shouldReceive('findOrFail')->once()->with('01memberunit')->andReturn($head);
    $this->memberFamilies->shouldReceive('assign')->once()->with($head, '01familyunit', FamilyRole::Head)
        ->andReturn([[], ['family_id' => '01familyunit', 'family_role' => 'head']]);
    $this->families->shouldReceive('fresh')->once()->with($this->family)->andReturn($this->family);

    $this->action->execute(new CreateFamilyDTO($this->actor, 'Boateng Family', headMemberId: '01memberunit'));

    Event::assertDispatched(MemberUpdated::class, fn (MemberUpdated $e) => $e->member === $head && $e->new['family_role'] === 'head');
});

it('never moves a member who already belongs to a family', function (): void {
    $member = unitMember(['family_id' => '01otherfamily'], persisted: true);
    $this->members->shouldReceive('findOrFail')->andReturn($member);
    $this->memberFamilies->shouldReceive('assign')->never();

    expect(fn () => $this->action->execute(new CreateFamilyDTO($this->actor, 'Boateng Family', headMemberId: '01memberunit')))
        ->toThrow(MemberAlreadyInFamilyException::class);
    Event::assertNotDispatched(MemberUpdated::class);
});
