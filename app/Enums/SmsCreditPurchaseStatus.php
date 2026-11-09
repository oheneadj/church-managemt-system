<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a purchase of SMS credits stands, stored as a plain string column (D-017).
 */
enum SmsCreditPurchaseStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
