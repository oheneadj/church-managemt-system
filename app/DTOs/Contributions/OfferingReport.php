<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\Models\Service;

/**
 * What one service received: the total, each fund's line, the total by payment method, and how many gifts there were.
 * Voided gifts are not in any number.
 */
final readonly class OfferingReport
{
    /**
     * Holds the service and the figures.
     *
     * @param  numeric-string  $grandTotal
     * @param  array<int, OfferingFund>  $byFund
     * @param  array<string, numeric-string>  $byMethod
     */
    public function __construct(
        public Service $service,
        public string $grandTotal,
        public array $byFund,
        public array $byMethod,
        public int $transactionCount,
        public int $anonymousCount,
    ) {}
}
