<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed family role values, stored as a plain string column (D-017).
 */
enum FamilyRole: string
{
    case Head = 'head';
    case Spouse = 'spouse';
    case Child = 'child';
    case Dependent = 'dependent';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Head => 'Head',
            self::Spouse => 'Spouse',
            self::Child => 'Child',
            self::Dependent => 'Dependent',
        };
    }
}
