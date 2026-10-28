<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to an audited record, stored as a plain string column (D-017).
 */
enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Viewed = 'viewed';
    case Exported = 'exported';
    case Erased = 'erased';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::StatusChanged => 'Status changed',
            self::Viewed => 'Viewed',
            self::Exported => 'Data exported',
            self::Erased => 'Personal data erased',
        };
    }
}
