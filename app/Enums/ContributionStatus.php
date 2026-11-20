<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a gift is in its life, stored as a plain string column (D-017, D-053). Cash is completed the moment it is recorded.
 * A digital gift starts pending when the payment starts and moves to completed or failed when the provider says so.
 * Only completed gifts are counted anywhere: totals, lists, pledges, receipts and payouts.
 */
enum ContributionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
