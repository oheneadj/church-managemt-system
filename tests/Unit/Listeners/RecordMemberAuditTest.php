<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\MemberStatus;
use App\Events\MemberCreated;
use App\Events\MemberStatusChanged;
use App\Events\MemberUpdated;
use App\Listeners\RecordMemberAudit;
use App\Models\AuditLog;
use App\Services\AuditLogger;

beforeEach(function (): void {
    $this->audit = Mockery::mock(AuditLogger::class);
    $this->listener = new RecordMemberAudit($this->audit);
    $this->actor = unitUser();
});

it('records a create, hiding the encrypted fields', function (): void {
    $member = unitMember(['address' => 'Secret Street 1', 'national_id_number' => 'GHA-123', 'occupation' => 'Nurse']);
    $this->audit->shouldReceive('record')->once()->withArgs(function ($subject, $event, $actor, $old, $new) use ($member): bool {
        return $subject === $member && $event === AuditEvent::Created && $actor === $this->actor && $old === []
            && $new['address'] === '[changed]' && $new['national_id_number'] === '[changed]' && $new['occupation'] === 'Nurse';
    })->andReturn(AuditLog::factory()->make(['church_id' => '01churchunit', 'auditable_id' => '01memberunit']));

    $this->listener->handle(new MemberCreated($member, $this->actor));
});

it('records an update with old and new values, hiding encrypted fields on both sides', function (): void {
    $member = unitMember();
    $this->audit->shouldReceive('record')->once()->with(
        $member,
        AuditEvent::Updated,
        $this->actor,
        ['phone' => '+233200000001', 'address' => '[changed]'],
        ['phone' => '+233200000002', 'address' => '[changed]'],
    )->andReturn(AuditLog::factory()->make(['church_id' => '01churchunit', 'auditable_id' => '01memberunit']));

    $this->listener->handle(new MemberUpdated($member, $this->actor, ['phone' => '+233200000001', 'address' => 'Old Street'], ['phone' => '+233200000002', 'address' => 'New Street']));
});

it('records a status change with the previous status and the reason', function (): void {
    $member = unitMember(['status' => MemberStatus::Inactive]);
    $this->audit->shouldReceive('record')->once()->with(
        $member,
        AuditEvent::StatusChanged,
        $this->actor,
        ['status' => 'active'],
        ['status' => 'inactive'],
        'Moved to Kumasi',
    )->andReturn(AuditLog::factory()->make(['church_id' => '01churchunit', 'auditable_id' => '01memberunit']));

    $this->listener->handle(new MemberStatusChanged($member, $this->actor, MemberStatus::Active, 'Moved to Kumasi'));
});
