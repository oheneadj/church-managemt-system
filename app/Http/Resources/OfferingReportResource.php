<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Contributions\OfferingFund;
use App\DTOs\Contributions\OfferingReport;
use App\Enums\PaymentMethod;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The offering report of a service (docs/08, Sunday Offering Report). Every amount is an exact decimal string with a
 * formatted sibling, and the formatting happens only in Money. It holds totals and no names.
 *
 * @property OfferingReport $resource
 */
class OfferingReportResource extends JsonResource
{
    /**
     * Shapes the report for the API. The amounts by payment method are an object keyed by method name.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'service' => [
                'id' => $report->service->id,
                'name' => $report->service->name,
                'service_date' => $report->service->service_date->toDateString(),
            ],
            'totals' => [
                ...$this->money('grand_total', $report->grandTotal),
                'by_fund' => $this->fundLines($report->byFund),
                'by_method' => $this->moneyByMethod($report->byMethod),
                'transaction_count' => $report->transactionCount,
                'anonymous_count' => $report->anonymousCount,
            ],
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
     * An amount and its display text, under the given name.
     *
     * @param  numeric-string  $amount
     * @return array<string, string>
     */
    private function money(string $name, string $amount): array
    {
        $money = Money::ghs($amount);

        return [$name => $money->amount, "{$name}_formatted" => $money->formatted()];
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
