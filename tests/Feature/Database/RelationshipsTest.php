<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\Family;
use App\Models\FundType;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\Pledge;
use App\Models\Service;
use App\Models\User;

// Every relationship is defined on both sides (Laravel addendum, section 4). Each pair is read from both ends.

beforeEach(function (): void {
    $this->church = Church::factory()->create();
    $this->staff = User::factory()->for($this->church)->create();
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'assigned_pastor_id' => $this->staff->id]);
    app()->instance('current.church', $this->church);
});

it('links a staff member to the members they pastor and the departments they head', function (): void {
    $department = Department::factory()->create(['church_id' => $this->church->id, 'head_user_id' => $this->staff->id]);

    expect($this->member->assignedPastor->is($this->staff))->toBeTrue()
        ->and($this->staff->assignedMembers->pluck('id')->all())->toBe([$this->member->id])
        ->and($department->head->is($this->staff))->toBeTrue()
        ->and($this->staff->headedDepartments->pluck('id')->all())->toBe([$department->id]);
});

it('links a family to its head from both ends', function (): void {
    $family = Family::factory()->create(['church_id' => $this->church->id, 'head_member_id' => $this->member->id]);

    expect($family->head->is($this->member))->toBeTrue()
        ->and($this->member->headedFamily->is($family))->toBeTrue();
});

it('links members and departments through their links from both ends', function (): void {
    $department = Department::factory()->create(['church_id' => $this->church->id]);
    $link = MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'department_id' => $department->id, 'role' => 'Leader']);

    expect($link->member->is($this->member))->toBeTrue()
        ->and($link->department->is($department))->toBeTrue()
        ->and($this->member->departmentLinks->pluck('id')->all())->toBe([$link->id])
        ->and($department->memberLinks->pluck('id')->all())->toBe([$link->id]);
});

it('links gifts and pledges to their fund, service and member from both ends', function (): void {
    $fund = FundType::factory()->create(['church_id' => $this->church->id]);
    $service = Service::factory()->create(['church_id' => $this->church->id]);
    $gift = Contribution::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $fund->id, 'service_id' => $service->id]);
    $pledge = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $fund->id]);

    expect($gift->fundType->is($fund))->toBeTrue()
        ->and($fund->contributions->pluck('id')->all())->toBe([$gift->id])
        ->and($gift->service->is($service))->toBeTrue()
        ->and($service->contributions->pluck('id')->all())->toBe([$gift->id])
        ->and($pledge->fundType->is($fund))->toBeTrue()
        ->and($fund->pledges->pluck('id')->all())->toBe([$pledge->id])
        ->and($pledge->member->is($this->member))->toBeTrue()
        ->and($this->member->pledges->pluck('id')->all())->toBe([$pledge->id]);
});

it('links a member to their change history, and a history line back to the member', function (): void {
    $line = AuditLog::factory()->create(['church_id' => $this->church->id, 'auditable_type' => Member::class, 'auditable_id' => $this->member->id, 'event' => AuditEvent::Updated]);

    expect($this->member->auditLogs->pluck('id')->all())->toBe([$line->id])
        ->and($line->auditable->is($this->member))->toBeTrue();
});
