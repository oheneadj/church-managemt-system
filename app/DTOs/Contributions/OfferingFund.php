<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

/**
 * One fund's line in the offering report: its name, what came in by each payment method and the total.
 */
final readonly class OfferingFund
{
    /**
     * Holds the fund name, the amount for every payment method (decimal strings) and the total.
     *
     * @param  array<string, numeric-string>  $byMethod
     * @param  numeric-string  $total
     */
    public function __construct(public string $name, public array $byMethod, public string $total) {}
}
