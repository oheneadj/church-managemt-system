<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the attendance trend report is grouped: one row for each week, or one row for each service.
 */
enum ReportGrouping: string
{
    case Week = 'week';
    case Service = 'service';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Week => 'By week',
            self::Service => 'By service',
        };
    }
}
