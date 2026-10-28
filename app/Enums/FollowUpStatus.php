<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed follow up status values, stored as a plain string column (D-017).
 */
enum FollowUpStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Lost = 'lost';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Contacted => 'Contacted',
            self::Converted => 'Converted',
            self::Lost => 'Lost',
        };
    }
}
