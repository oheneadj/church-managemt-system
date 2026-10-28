<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed church plan values, stored as a plain string column (D-017).
 */
enum ChurchPlan: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Growth => 'Growth',
            self::Pro => 'Pro',
            self::Enterprise => 'Enterprise',
        };
    }
}
