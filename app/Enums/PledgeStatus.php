<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed pledge status values, stored as a plain string column (D-017).
 */
enum PledgeStatus: string
{
    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Partial = 'partial';
    case Lapsed = 'lapsed';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Fulfilled => 'Fulfilled',
            self::Partial => 'Partial',
            self::Lapsed => 'Lapsed',
        };
    }
}
