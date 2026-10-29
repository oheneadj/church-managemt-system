<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Contributions\OfferingFund;
use App\DTOs\Contributions\OfferingReport;
use App\DTOs\Contributions\OfferingSection;
use App\Enums\PaymentMethod;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The offering report of a service (docs/08, Sunday Offering Report). Every amount is an exact decimal string with a
 * formatted sibling, and the formatting happens only in Money. It holds totals and no names.
 *
 * `totals` are the gifts recorded at the service. `no_service` are the gifts given that day with no service; they are
 * counted with the day's first service only (`counted_here`), and `day_grand_total` is the two added together.
 *
 * @property OfferingReport $resource
 */
class OfferingReportResource extends JsonResource
{
    /**
     * Shapes the report for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;
        $day = Money::ghs(bcadd($report->totals->grandTotal, $report->noService->grandTotal, 2));

        return [
            'service' => [
                'id' => $report->service->id,
                'name' => $report->service->name,
                'service_date' => $report->service->service_date->toDateString(),
            ],
            'totals' => $this->section($report->totals),
            'no_service' => [
                'counted_here' => $report->noServiceCountedHere,
                ...$this->section($report->noService),
            ],
            'day_grand_total' => $day->amount,
            'day_grand_total_formatted' => $day->formatted(),
        ];
    }

    /**
     * One set of figures: the total, each fund's line, the total by payment method and the counts.
     *
     * @return array<string, mixed>
     */
    private function section(OfferingSection $section): array
    {
        $grand = Money::ghs($section->grandTotal);

        return [
            'grand_total' => $grand->amount,
            'grand_total_formatted' => $grand->formatted(),
            'by_fund' => $this->fundLines($section->byFund),
            'by_method' => $this->moneyByMethod($section->byMethod),
            'transaction_count' => $section->transactionCount,
            'anonymous_count' => $section->anonymousCount,
        ];
    }

    /**
     * One line for each fund that received something.
     *
     * @param  array<int, OfferingFund>  $funds
     * @return array<int, array{fund_type: string, by_method: array{cash: string, momo: string, card: string, ussd: string}, total: string, total_formatted: string}>
     */
    private function fundLines(array $funds): array
    {
        $lines = [];

        foreach ($funds as $fund) {
            $lines[] = $this->fundLine($fund);
        }

        return $lines;
    }

    /**
     * One fund's line: its name, what came in by each payment method and its total.
     *
     * @return array{fund_type: string, by_method: array{cash: string, momo: string, card: string, ussd: string}, total: string, total_formatted: string}
     */
    private function fundLine(OfferingFund $fund): array
    {
        $total = Money::ghs($fund->total);

        return [
            'fund_type' => $fund->name,
            'by_method' => $this->moneyByMethod($fund->byMethod),
            'total' => $total->amount,
            'total_formatted' => $total->formatted(),
        ];
    }

    /**
     * Each payment method's amount, as a decimal string. The methods are written out one by one, so the API
     * documentation can list them as named fields.
     *
     * @param  array<string, numeric-string>  $amounts
     * @return array{cash: string, momo: string, card: string, ussd: string}
     */
    private function moneyByMethod(array $amounts): array
    {
        return [
            PaymentMethod::Cash->value => Money::ghs($amounts[PaymentMethod::Cash->value])->amount,
            PaymentMethod::Momo->value => Money::ghs($amounts[PaymentMethod::Momo->value])->amount,
            PaymentMethod::Card->value => Money::ghs($amounts[PaymentMethod::Card->value])->amount,
            PaymentMethod::Ussd->value => Money::ghs($amounts[PaymentMethod::Ussd->value])->amount,
        ];
    }
}
