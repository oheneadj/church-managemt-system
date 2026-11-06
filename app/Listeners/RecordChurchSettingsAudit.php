<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\ChurchSettingsUpdated;
use App\Services\AuditLogger;

/**
 * Writes the change history line for a settings change. It is not queued on purpose: the line must be saved in the same
 * transaction as the change, so a rolled-back change leaves no history behind.
 */
class RecordChurchSettingsAudit
{
    /**
     * Takes the audit logger.
     */
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Records which settings changed, with their old and new values.
     */
    public function handle(ChurchSettingsUpdated $event): void
    {
        $this->audit->record($event->church, AuditEvent::Updated, $event->actor, $event->old, $event->new);
    }
}
