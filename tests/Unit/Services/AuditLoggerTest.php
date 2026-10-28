<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Repositories\AuditLogRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Context;

it('appends a line naming the record, the actor, the changed fields and the request', function (): void {
    Context::add('request_id', 'req-12345678');
    $logs = Mockery::mock(AuditLogRepositoryInterface::class);
    $logs->shouldReceive('append')->once()->with([
        'church_id' => '01churchunit',
        'auditable_type' => 'App\Models\Member',
        'auditable_id' => '01memberunit',
        'event' => AuditEvent::Updated,
        'old_values' => ['occupation' => 'Teacher'],
        'new_values' => ['occupation' => 'Nurse'],
        'changed_fields' => ['occupation'],
        'actor_id' => '01userunit',
        'actor_name' => 'Akosua Mensah',
        'request_id' => 'req-12345678',
        'reason' => 'Promoted',
    ])->andReturn(new AuditLog);

    (new AuditLogger($logs))->record(unitMember(), AuditEvent::Updated, unitUser(), ['occupation' => 'Teacher'], ['occupation' => 'Nurse'], 'Promoted');
});

it('stores nothing for the old or new values of a create or a status change that has none', function (): void {
    $logs = Mockery::mock(AuditLogRepositoryInterface::class);
    $logs->shouldReceive('append')->once()->withArgs(fn (array $a) => $a['old_values'] === null && $a['new_values'] === null && $a['changed_fields'] === [])->andReturn(new AuditLog);

    (new AuditLogger($logs))->record(unitMember(), AuditEvent::Created, unitUser());
});
