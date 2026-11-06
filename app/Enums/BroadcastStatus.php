<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a broadcast is: waiting for a worker, being turned into texts, all texts queued, or failed before it finished.
 */
enum BroadcastStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Dispatched = 'dispatched';
    case Failed = 'failed';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Dispatched => 'Sent to the gateway',
            self::Failed => 'Failed',
        };
    }
}
