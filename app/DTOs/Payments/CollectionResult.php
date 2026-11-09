<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Enums\ProviderPaymentStatus;

/**
 * What a payment provider said about one collection (docs/09). The provider's own response shape never leaves the driver.
 * Amounts are exact decimal strings in GHS. `fee` is null until the provider settles the payment, and `received` is what the
 * provider credited to us, also null until it is known.
 */
final readonly class CollectionResult
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
