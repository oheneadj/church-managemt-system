<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Contributions\OfferingFund;
use App\DTOs\Contributions\OfferingReport;
use App\Enums\PaymentMethod;
use App\Models\Service;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\FundTypeRepositoryInterface;

/**
 * Builds the offering report of a service (docs/04, Sunday Offering Report). The database adds the gifts up per fund
 * and payment method; this only arranges those lines and adds them across, with exact decimal arithmetic. Every
 * payment method is listed for every fund, even at 0.00, so no gift can be left out of the columns.
 */
class OfferingReportBuilder
{
    /**
     * Takes the repositories.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly FundTypeRepositoryInterface $fundTypes,
    ) {}

    /**
     * The report for this service.
     */
    public function build(Service $service): OfferingReport
    {
        $lines = $this->contributions->offeringTotalsForService($service->id);
        $methods = array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases());

        $byMethod = array_fill_keys($methods, '0.00');
        $perFund = [];
        $transactions = 0;
        $grand = '0.00';

        foreach ($lines as $line) {
            $perFund[$line['fund_type_id']][$line['payment_method']] = $line['total'];
            $byMethod[$line['payment_method']] = bcadd($byMethod[$line['payment_method']] ?? '0.00', $line['total'], 2);
            $grand = bcadd($grand, $line['total'], 2);
            $transactions += $line['gifts'];
        }

        return new OfferingReport(
            $service,
            $grand,
            $this->fundLines(array_keys($perFund), $perFund, $methods),
            $byMethod,
            $transactions,
            $this->contributions->anonymousCountForService($service->id),
        );
    }

    /**
     * One line per fund that received something, in the fund display order.
     *
     * @param  list<string>  $fundIds
     * @param  array<string, array<string, numeric-string>>  $perFund
     * @param  list<string>  $methods
     * @return array<int, OfferingFund>
     */
    private function fundLines(array $fundIds, array $perFund, array $methods): array
    {
        return $this->fundTypes->findMany($fundIds)->map(function ($fund) use ($perFund, $methods): OfferingFund {
            $amounts = array_merge(array_fill_keys($methods, '0.00'), $perFund[$fund->id]);

            return new OfferingFund($fund->name, $amounts, array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00'));
        })->values()->all();
    }
}
