<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed payout method values, stored as a plain string column (D-017).
 */
enum PayoutMethod: string
{
    case Momo = 'momo';
    case Bank = 'bank';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Momo => 'Mobile Money',
            self::Bank => 'Bank',
        };
    }
}
