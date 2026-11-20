<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\ChurchUpdated;
use App\Services\AuditLogger;

/**
 * Writes the change history line for a change to a church's profile or settings. It is not queued on purpose: the line must be saved in the same
 * transaction as the change, so a rolled-back change leaves no history behind.
 */
class RecordChurchAudit
{
    /**
     * Takes the audit logger.
     */
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Records which profile fields or settings changed, with their old and new values.
     */
    public function handle(ChurchUpdated $event): void
    {
        $this->audit->record($event->church, AuditEvent::Updated, $event->actor, $event->old, $event->new);
    }
}
