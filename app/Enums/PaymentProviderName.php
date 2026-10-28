<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed payment provider name values, stored as a plain string column (D-017).
 */
enum PaymentProviderName: string
{
    case Payperlez = 'payperlez';
    case Moolre = 'moolre';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Payperlez => 'PayPerlez',
            self::Moolre => 'Moolre',
        };
    }
}
