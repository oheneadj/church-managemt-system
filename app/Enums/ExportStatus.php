<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an export is: waiting for a worker, being built, ready, or failed for good.
 */
enum ExportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
        };
    }
}
