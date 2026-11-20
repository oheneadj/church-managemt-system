<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a church's automatic payouts are sent (D-018, `churches.payout_method`): a mobile money wallet or a bank account.
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
            self::Momo => 'Mobile money',
            self::Bank => 'Bank account',
        };
    }
}
