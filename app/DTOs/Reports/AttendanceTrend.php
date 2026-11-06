<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use App\Enums\ReportGrouping;

/**
 * The attendance trend report: rows (one per week or one per service, newest first) and the average, highest and lowest
 * attendance over the weeks or services in which something was recorded.
 */
final readonly class AttendanceTrend
{
    /**
     * Holds the grouping, the rows and the summary figures.
     *
     * @param  list<array<string, int|string>>  $rows
     */
    public function __construct(
        public ReportGrouping $groupBy,
        public array $rows,
        public int $average,
        public int $highest,
        public int $lowest,
    ) {}
}
