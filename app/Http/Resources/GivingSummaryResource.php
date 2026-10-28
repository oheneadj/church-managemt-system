<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a member has given this year, this month and in total. Each amount is an exact decimal string with a
 * formatted sibling, and the formatting happens only in Money.
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
        $summary = [];

        foreach (['this_year', 'this_month', 'all_time'] as $period) {
            $money = Money::ghs($this->resource[$period]);
            $summary[$period] = $money->amount;
            $summary[$period.'_formatted'] = $money->formatted();
        }

        return $summary;
    }
}
