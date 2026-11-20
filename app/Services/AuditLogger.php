<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Writes the append-only change history (patterns/Pattern — Audit Trails.md).
 * Callers pass only the fields that changed, and never secrets: encrypted fields are recorded by name only.
 */
class AuditLogger
{
    /**
     * Takes the audit log repository.
     */
    public function __construct(private readonly AuditLogRepositoryInterface $logs) {}

    /**
     * Appends one change-history line describing what happened, who did it and why.
     *
     * @param  array<string, mixed>  $old  previous values of the changed fields (empty for a create)
     * @param  array<string, mixed>  $new  new values of the changed fields
     */
    public function record(Model $subject, AuditEvent $event, User $actor, array $old = [], array $new = [], ?string $reason = null): AuditLog
    {
        return $this->logs->append([
            'church_id' => $actor->church_id,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'event' => $event,
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'changed_fields' => array_keys($new),
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'request_id' => Context::get('request_id'),
            'reason' => $reason,
        ]);
    }
}
