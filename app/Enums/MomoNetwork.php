<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed momo network values, stored as a plain string column (D-017).
 */
enum MomoNetwork: string
{
    case Mtn = 'mtn';
    case Telecel = 'telecel';
    case At = 'at';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mtn => 'MTN',
            self::Telecel => 'Telecel',
            self::At => 'AirtelTigo',
        };
    }
}
