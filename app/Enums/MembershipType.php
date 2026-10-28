<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed membership type values, stored as a plain string column (D-017).
 */
enum MembershipType: string
{
    case Full = 'full';
    case Associate = 'associate';
    case Affiliate = 'affiliate';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full',
            self::Associate => 'Associate',
            self::Affiliate => 'Affiliate',
        };
    }
}
