<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed disbursement status values, stored as a plain string column (D-017).
 */
enum DisbursementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }
}
