<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

/**
 * The giving trend report: one row for each month (newest first) with its total and its total for each fund, and a summary of
 * the whole period against the same number of months before it. Amounts are exact two-place decimal strings in cedis.
 */
final readonly class GivingTrend
{
    /**
     * Holds the monthly rows and the summary figures.
     *
     * @param  list<array{month: string, month_start: string, total: numeric-string, by_fund: list<array{fund_type_id: string, name: string, total: numeric-string}>}>  $months
     * @param  numeric-string  $total
     * @param  numeric-string  $previousTotal
     * @param  float|null  $changePercent  null when nothing was given in the months before
     */
    public function __construct(
        public array $months,
        public string $total,
        public string $previousTotal,
        public ?float $changePercent,
    ) {}
}
