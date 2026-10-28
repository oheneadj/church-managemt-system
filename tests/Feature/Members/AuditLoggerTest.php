<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Context;

beforeEach(function (): void {
    $this->church = Church::factory()->create();
    $this->actor = User::factory()->for($this->church)->create(['name' => 'Akosua Mensah']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id]);
});

it('records who changed what, with the old and new values', function (): void {
    $log = app(AuditLogger::class)->record(
        $this->member,
        AuditEvent::Updated,
        $this->actor,
        old: ['occupation' => 'Teacher'],
        new: ['occupation' => 'Nurse'],
    );

    expect($log->church_id)->toBe($this->church->id)
        ->and($log->auditable_type)->toBe(Member::class)
        ->and($log->auditable_id)->toBe($this->member->id)
        ->and($log->event)->toBe(AuditEvent::Updated)
        ->and($log->old_values)->toBe(['occupation' => 'Teacher'])
        ->and($log->new_values)->toBe(['occupation' => 'Nurse'])
        ->and($log->changed_fields)->toBe(['occupation'])
        ->and($log->actor_id)->toBe($this->actor->id);
});

it('keeps the actor name as it was at the time', function (): void {
    $log = app(AuditLogger::class)->record($this->member, AuditEvent::Created, $this->actor);

    $this->actor->update(['name' => 'Renamed Person']);

    expect($log->fresh()->actor_name)->toBe('Akosua Mensah');
});

it('ties the row to the request id and keeps the reason', function (): void {
    Context::add('request_id', 'req-12345678');

    $log = app(AuditLogger::class)->record($this->member, AuditEvent::StatusChanged, $this->actor, ['status' => 'active'], ['status' => 'inactive'], 'Moved to Kumasi');

    expect($log->request_id)->toBe('req-12345678')->and($log->reason)->toBe('Moved to Kumasi');
});

it('stores nothing for the old values of a create', function (): void {
    $log = app(AuditLogger::class)->record($this->member, AuditEvent::Created, $this->actor, new: ['first_name' => 'Abena']);

    expect($log->old_values)->toBeNull();
});

it('has no updated_at, because a row is written once', function (): void {
    $log = AuditLog::factory()->create(['church_id' => $this->church->id, 'auditable_id' => $this->member->id]);

    expect($log->updated_at)->toBeNull()->and($log->created_at)->not->toBeNull();
});

it('is scoped to the current church', function (): void {
    AuditLog::factory()->create(['church_id' => $this->church->id, 'auditable_id' => $this->member->id]);
    $other = Church::factory()->create();
    app()->instance('current.church', $other);

    expect(AuditLog::query()->count())->toBe(0);
});
