<?php

declare(strict_types=1);

use App\Actions\Members\CreateMemberAction;
use App\DTOs\Members\CreateMemberDTO;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Events\MemberCreated;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberDepartmentService;
use App\Services\MemberFamilyService;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([MemberCreated::class]);
    withoutDatabaseTransactions();
    $this->actor = unitUser();
    $this->members = Mockery::mock(MemberRepositoryInterface::class);
    $this->numbers = Mockery::mock(MemberNumberGenerator::class);
    $this->families = Mockery::mock(MemberFamilyService::class);
    $this->departments = Mockery::mock(MemberDepartmentService::class);
    $this->action = new CreateMemberAction($this->members, $this->numbers, $this->families, $this->departments);
    $this->dto = new CreateMemberDTO(
        actor: $this->actor,
        firstName: 'Abena',
        lastName: 'Asante',
        gender: Gender::Female,
        phone: '+233200987654',
        departmentIds: ['01dept'],
    );
});

it('numbers the member, checks the family, saves, sets departments and fires MemberCreated', function (): void {
    $this->numbers->shouldReceive('next')->once()->with('01churchunit')->andReturn('GRC-0001');
    $this->families->shouldReceive('guard')->once()->with(Mockery::type(Member::class))->ordered();
    $this->members->shouldReceive('save')->once()->andReturnUsing(fn (Member $m) => $m)->ordered();
    $this->families->shouldReceive('claimHead')->once()->with(Mockery::type(Member::class))->ordered();
    $this->departments->shouldReceive('sync')->once()->with(Mockery::type(Member::class), ['01dept'])->ordered();
    $this->members->shouldReceive('loadDetail')->once()->with(Mockery::type(Member::class), true)->andReturnUsing(fn (Member $m) => $m);

    $member = $this->action->execute($this->dto);

    expect($member->member_number)->toBe('GRC-0001')
        ->and($member->church_id)->toBe('01churchunit')
        ->and($member->created_by)->toBe('01userunit')
        ->and($member->first_name)->toBe('Abena')
        ->and($member->status)->toBe(MemberStatus::Active)
        ->and($member->status_changed_at)->not->toBeNull();
    Event::assertDispatched(MemberCreated::class, fn (MemberCreated $e) => $e->member === $member && $e->actor === $this->actor);
});

it('fires nothing when saving fails', function (): void {
    $this->numbers->shouldReceive('next')->andReturn('GRC-0001');
    $this->families->shouldReceive('guard');
    $this->members->shouldReceive('save')->andThrow(new RuntimeException('database down'));

    expect(fn () => $this->action->execute($this->dto))->toThrow(RuntimeException::class);
    Event::assertNotDispatched(MemberCreated::class);
});

it('stops before saving when the family rules refuse', function (): void {
    $this->numbers->shouldReceive('next')->andReturn('GRC-0001');
    $this->families->shouldReceive('guard')->andThrow(new RuntimeException('second head'));
    $this->members->shouldReceive('save')->never();

    expect(fn () => $this->action->execute($this->dto))->toThrow(RuntimeException::class);
    Event::assertNotDispatched(MemberCreated::class);
});
