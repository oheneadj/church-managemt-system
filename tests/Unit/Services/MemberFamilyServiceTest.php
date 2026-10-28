<?php

declare(strict_types=1);

use App\Enums\FamilyRole;
use App\Models\Family;
use App\Repositories\FamilyRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberFamilyService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->families = Mockery::mock(FamilyRepositoryInterface::class);
    $this->members = Mockery::mock(MemberRepositoryInterface::class);
    $this->service = new MemberFamilyService($this->families, $this->members);
});

describe('guard', function (): void {
    it('refuses a family role with no family', function (): void {
        $member = unitMember(['family_id' => null, 'family_role' => FamilyRole::Spouse]);

        expect(fn () => $this->service->guard($member))->toThrow(ValidationException::class);
    });

    it('lets a member with no family and no role through without touching the database', function (): void {
        $this->families->shouldReceive('lock')->never();

        $this->service->guard(unitMember());
    });

    it('does not lock the family for a role other than head', function (): void {
        $this->families->shouldReceive('lock')->never();

        $this->service->guard(unitMember(['family_id' => '01family', 'family_role' => FamilyRole::Child]));
    });

    it('locks the family first, then refuses a second head', function (): void {
        $this->families->shouldReceive('lock')->once()->with('01family')->andReturn(new Family)->ordered();
        $this->members->shouldReceive('familyHasOtherHead')->once()->with('01family', '01memberunit')->andReturnTrue()->ordered();
        $member = unitMember(['family_id' => '01family', 'family_role' => FamilyRole::Head], persisted: true);

        expect(fn () => $this->service->guard($member))->toThrow(ValidationException::class);
    });

    it('lets the only head through, and does not exclude a member that is not saved yet', function (): void {
        $this->families->shouldReceive('lock')->once()->andReturn(new Family);
        $this->members->shouldReceive('familyHasOtherHead')->once()->with('01family', null)->andReturnFalse();

        $this->service->guard(unitMember(['family_id' => '01family', 'family_role' => FamilyRole::Head]));
    });
});

describe('claimHead', function (): void {
    it('records the head on the family', function (): void {
        $this->families->shouldReceive('setHead')->once()->with('01family', '01memberunit');

        $this->service->claimHead(unitMember(['family_id' => '01family', 'family_role' => FamilyRole::Head]));
    });

    it('does nothing for any other role', function (): void {
        $this->families->shouldReceive('setHead')->never();

        $this->service->claimHead(unitMember(['family_id' => '01family', 'family_role' => FamilyRole::Spouse]));
    });
});

it('assigns a member to a family and returns what changed', function (): void {
    $member = unitMember(['family_id' => null, 'family_role' => null], persisted: true);
    $this->families->shouldReceive('lock')->once()->andReturn(new Family);
    $this->members->shouldReceive('familyHasOtherHead')->once()->andReturnFalse();
    $this->members->shouldReceive('save')->once()->with($member)->andReturn($member);
    $this->families->shouldReceive('setHead')->once()->with('01family', '01memberunit');

    [$old, $new] = $this->service->assign($member, '01family', FamilyRole::Head);

    expect($old)->toBe(['family_id' => null, 'family_role' => null])
        ->and($new)->toBe(['family_id' => '01family', 'family_role' => 'head']);
});
