<?php

declare(strict_types=1);

namespace App\DTOs\Members;

/**
 * What a member has given this year, this month and in total, as exact decimal strings in GHS.
 */
final readonly class GivingSummary
{
    /**
     * Holds the three totals, each an exact decimal string such as "2400.00".
     */
    public function __construct(public string $thisYear, public string $thisMonth, public string $allTime) {}
}
