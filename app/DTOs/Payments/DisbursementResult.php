<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Enums\ProviderPaymentStatus;

/**
 * What a payment provider said about one payout to a church (docs/09). Amounts are exact decimal strings in GHS. `amount` is the
 * most we asked to spend, provider fee included; `fee` is what the provider charged and `received` what the church was sent
 * (`amount` minus `fee`), both null until the provider reports them.
 */
final readonly class DisbursementResult
{
    /**
     * Holds the normalised answer.
     */
    public function __construct(
        public string $reference,
        public string $clientReference,
        public ProviderPaymentStatus $status,
        public string $amount,
        public ?string $fee,
        public ?string $received,
    ) {}
}
