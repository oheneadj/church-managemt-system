<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

/**
 * One set of offering figures: the total, each fund's line, the total by payment method, and how many gifts there were
 * and how many of those had no member. Voided gifts are not in any number.
 */
final readonly class OfferingSection
{
    /**
     * Holds the figures.
     *
     * @param  numeric-string  $grandTotal
     * @param  array<int, OfferingFund>  $byFund
     * @param  array<string, numeric-string>  $byMethod
     */
    public function __construct(
        public string $grandTotal,
        public array $byFund,
        public array $byMethod,
        public int $transactionCount,
        public int $anonymousCount,
    ) {}
}
