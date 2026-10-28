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

    /**
     * Takes the audit logger.
     */
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Records that a member was added.
     */
    public function handleCreated(MemberCreated $event): void
    {
        $this->audit->record($event->member, AuditEvent::Created, $event->actor, new: $this->redact($event->member->getAttributes()));
    }

    /**
     * Records which fields of a member changed, with their old and new values.
     */
    public function handleUpdated(MemberUpdated $event): void
    {
        $this->audit->record($event->member, AuditEvent::Updated, $event->actor, $this->redact($event->old), $this->redact($event->new));
    }

    /**
     * Records a status change with the previous status and the reason given.
     */
    public function handleStatusChanged(MemberStatusChanged $event): void
    {
        $this->audit->record(
            $event->member,
            AuditEvent::StatusChanged,
            $event->actor,
            ['status' => $event->previous->value],
            ['status' => $event->member->status->value],
            $event->note,
        );
    }

    /**
     * Records that someone opened the member's record, which shows the encrypted address. The value is not logged.
     */
    public function handleViewed(MemberViewed $event): void
    {
        $this->audit->record($event->member, AuditEvent::Viewed, $event->viewer, new: ['address' => '[viewed]']);
    }

    /**
     * Replaces the values of encrypted fields with a marker before they are logged.
     *
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
