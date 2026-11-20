<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a mobile money gift failed, stored as a plain string column (D-017). Only an expired gift can still be counted if the giver pays
 * late: the provider never said it failed, we gave up waiting.
 */
enum ContributionFailureReason: string
{
    case Expired = 'expired';
    case Declined = 'declined';
    case Refused = 'refused';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Expired => 'The payment was not approved in time',
            self::Declined => 'The payment was not completed',
            self::Refused => 'The payment could not be started',
        };
    }
}
