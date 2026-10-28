<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed credit ledger type values, stored as a plain string column (D-017).
 */
enum CreditLedgerType: string
{
    case Allocation = 'allocation';
    case Topup = 'topup';
    case Consumption = 'consumption';
    case Refund = 'refund';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Allocation => 'Allocation',
            self::Topup => 'Topup',
            self::Consumption => 'Consumption',
            self::Refund => 'Refund',
        };
    }
}
