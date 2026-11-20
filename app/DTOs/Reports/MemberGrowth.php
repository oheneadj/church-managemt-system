<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

/**
 * The member growth report: one row for each month (newest first) with the members who became active, the members who left
 * active membership, the net of the two and the active total at the month's end, and the same sums for the whole period.
 */
final readonly class MemberGrowth
{
    /**
     * Holds the monthly rows and the period totals.
     *
     * @param  list<array{month: string, month_start: string, new: int, lost: int, net: int, active_total: int}>  $months
     */
    public function __construct(public array $months, public int $new, public int $lost, public int $net) {}
}
