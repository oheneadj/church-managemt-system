<?php

declare(strict_types=1);

use App\Enums\CheckInMethod;
use App\Enums\MemberStatus;
use App\Models\Service;
use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Services\AttendanceRecorder;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function (): void {
    $this->members = Mockery::mock(MemberRepositoryInterface::class);
    $this->attendance = Mockery::mock(AttendanceRecordRepositoryInterface::class);
    $this->recorder = new AttendanceRecorder($this->members, $this->attendance);
    $this->service = Service::factory()->make(['id' => 'svc', 'church_id' => 'church', 'created_by' => 'user']);
});

it('records the members it finds and counts the rest as already in', function (): void {
    $this->members->shouldReceive('findMany')->once()->andReturn(new Collection([unitMember(['id' => 'a']), unitMember(['id' => 'b']), unitMember(['id' => 'c'])]));
    $this->attendance->shouldReceive('insertMembers')->once()->withArgs(fn ($service, $ids, $method) => $ids === ['a', 'b', 'c'] && $method === CheckInMethod::Whatsapp)->andReturn(1);

    $result = $this->recorder->recordMembers($this->service, ['a', 'b', 'c'], CheckInMethod::Whatsapp);

    expect($result->recorded)->toBe(1)->and($result->alreadyCheckedIn)->toBe(2)->and($result->errors)->toBe([]);
});

it('reports ids that were not found and members who have died, and records the rest', function (): void {
    $this->members->shouldReceive('findMany')->andReturn(new Collection([unitMember(['id' => 'a']), unitMember(['id' => 'd', 'status' => MemberStatus::Deceased])]));
    $this->attendance->shouldReceive('insertMembers')->once()->withArgs(fn ($service, $ids) => $ids === ['a'])->andReturn(1);

    $result = $this->recorder->recordMembers($this->service, ['a', 'd', 'missing'], CheckInMethod::Manual);

    expect($result->recorded)->toBe(1)->and($result->errors)->toBe([
        ['member_id' => 'd', 'message' => 'This member cannot be checked in.'],
        ['member_id' => 'missing', 'message' => 'Member not found.'],
    ]);
});

it('does not touch the database when nobody can be checked in', function (): void {
    $this->members->shouldReceive('findMany')->andReturn(new Collection);
    $this->attendance->shouldReceive('insertMembers')->once()->with($this->service, [], CheckInMethod::Manual, Mockery::any())->andReturn(0);

    $result = $this->recorder->recordMembers($this->service, ['x'], CheckInMethod::Manual);

    expect($result->recorded)->toBe(0)->and($result->alreadyCheckedIn)->toBe(0)->and($result->errors)->toHaveCount(1);
});
