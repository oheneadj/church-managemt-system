<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Members\GivingSummary;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a member has given this year, this month and in total. Each amount is an exact decimal string with a
 * formatted sibling, and the formatting happens only in Money.
 *
 * @property GivingSummary $resource
 */
class GivingSummaryResource extends JsonResource
{
    /**
     * Shapes the three totals, each as a raw amount and a display string.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $year = Money::ghs($this->resource->thisYear);
        $month = Money::ghs($this->resource->thisMonth);
        $all = Money::ghs($this->resource->allTime);

        return [
            'this_year' => $year->amount,
            'this_year_formatted' => $year->formatted(),
            'this_month' => $month->amount,
            'this_month_formatted' => $month->formatted(),
            'all_time' => $all->amount,
            'all_time_formatted' => $all->formatted(),
        ];
    }
}
