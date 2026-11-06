<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\StaffInvited;
use App\Events\StaffUpdated;
use App\Services\AuditLogger;

/**
 * Writes the change history line for a staff event (D-055 point 7). It is not queued on purpose: the line must be saved in
 * the same transaction as the change, so a rolled-back change leaves no history behind. No password or code is ever logged.
 */
class RecordStaffAudit
{
    /**
     * Takes the audit logger.
     */
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Records that a staff member was invited, with the details the admin gave.
     */
    public function handleInvited(StaffInvited $event): void
    {
        $this->audit->record($event->staff, AuditEvent::Created, $event->actor, new: [
            'name' => $event->staff->name,
            'email' => $event->staff->email,
            'phone' => $event->staff->phone,
            'role' => $event->role,
        ]);
    }

    /**
     * Records which of name, phone and role changed, with their old and new values.
     */
    public function handleUpdated(StaffUpdated $event): void
    {
        $this->audit->record($event->staff, AuditEvent::Updated, $event->actor, $event->old, $event->new);
    }
}
