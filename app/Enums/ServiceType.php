<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed service type values, stored as a plain string column (D-017).
 */
enum ServiceType: string
{
    case Sunday = 'sunday';
    case Midweek = 'midweek';
    case Special = 'special';
    case Youth = 'youth';
    case Children = 'children';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sunday => 'Sunday',
            self::Midweek => 'Midweek',
            self::Special => 'Special',
            self::Youth => 'Youth',
            self::Children => 'Children',
        };
    }
}
