<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Reports\GivingTrend;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The giving trend report (docs/08, Giving Trends). Every amount is an exact decimal string in cedis with a formatted
 * sibling, and the formatting happens only in Money. It holds totals and no names of members.
 *
 * @property GivingTrend $resource
 */
class GivingTrendResource extends JsonResource
{
    /** The report has `data` and `summary` side by side, so it is not wrapped in another `data`. */
    public static $wrap = null;

    /**
     * Shapes the report for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'data' => array_map(fn (array $month): array => [
                'month' => $month['month'],
                'month_start' => $month['month_start'],
                'total' => $month['total'],
                'total_formatted' => Money::ghs($month['total'])->formatted(),
                'by_fund' => array_map(fn (array $fund): array => [
                    'fund_type_id' => $fund['fund_type_id'],
                    'name' => $fund['name'],
                    'total' => $fund['total'],
                    'total_formatted' => Money::ghs($fund['total'])->formatted(),
                ], $month['by_fund']),
            ], $report->months),
            'summary' => [
                'total' => $report->total,
                'total_formatted' => Money::ghs($report->total)->formatted(),
                'previous_total' => $report->previousTotal,
                'previous_total_formatted' => Money::ghs($report->previousTotal)->formatted(),
                'change_percent' => $report->changePercent,
            ],
        ];
    }
}
