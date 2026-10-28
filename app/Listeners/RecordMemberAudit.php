<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\MemberCreated;
use App\Events\MemberStatusChanged;
use App\Events\MemberUpdated;
use App\Events\MemberViewed;
use App\Services\AuditLogger;

/**
 * Writes the change history line for a member event (D-030). It is not queued on purpose: the line must be
 * saved in the same transaction as the change, so a rolled-back change leaves no history behind.
 */
class RecordMemberAudit
{
    /** Fields whose values are encrypted at rest; the change log records that they changed, never what to. */
    private const SENSITIVE = ['address', 'national_id_number'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(MemberCreated|MemberUpdated|MemberStatusChanged|MemberViewed $event): void
    {
        match (true) {
            $event instanceof MemberCreated => $this->audit->record(
                $event->member,
                AuditEvent::Created,
                $event->actor,
                new: $this->redact($event->member->getAttributes()),
            ),
            $event instanceof MemberUpdated => $this->audit->record(
                $event->member,
                AuditEvent::Updated,
                $event->actor,
                $this->redact($event->old),
                $this->redact($event->new),
            ),
            $event instanceof MemberViewed => $this->audit->record(
                $event->member,
                AuditEvent::Viewed,
                $event->viewer,
                new: ['address' => '[viewed]'],
            ),
            default => $this->audit->record(
                $event->member,
                AuditEvent::StatusChanged,
                $event->actor,
                ['status' => $event->previous->value],
                ['status' => $event->member->status->value],
                $event->note,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach (self::SENSITIVE as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[changed]';
            }
        }

        return $values;
    }
}
