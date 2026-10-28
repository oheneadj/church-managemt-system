<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed payment method values, stored as a plain string column (D-017).
 */
enum PaymentMethod: string
{
    case Momo = 'momo';
    case Card = 'card';
    case Cash = 'cash';
    case Ussd = 'ussd';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Momo => 'Mobile Money',
            self::Card => 'Card',
            self::Cash => 'Cash',
            self::Ussd => 'USSD',
        };
    }
}
