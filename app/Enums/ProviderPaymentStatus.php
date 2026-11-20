<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a payment provider says about one collection or payout, in our own words (docs/09). Anything a provider says that we do
 * not recognise is `Unknown`: it is polled again, never assumed to have worked, because money is involved.
 */
enum ProviderPaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * Whether the provider has given its final word. A pending or unknown payment is not final and is polled again.
     */
    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
