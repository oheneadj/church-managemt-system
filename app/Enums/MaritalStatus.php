<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed marital status values, stored as a plain string column (D-017).
 */
enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Divorced = 'divorced';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Widowed => 'Widowed',
            self::Divorced => 'Divorced',
        };
    }
}
