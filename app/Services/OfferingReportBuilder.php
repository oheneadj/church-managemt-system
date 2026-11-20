<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Contributions\OfferingFund;
use App\DTOs\Contributions\OfferingReport;
use App\DTOs\Contributions\OfferingSection;
use App\Enums\PaymentMethod;
use App\Models\Service;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\FundTypeRepositoryInterface;
use App\Repositories\ServiceRepositoryInterface;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Builds the offering report of a service (docs/04, Sunday Offering Report). The database adds the gifts up per fund
 * and payment method; this only arranges those lines and adds them across, with exact decimal arithmetic. Every
 * payment method is listed for every fund, even at 0.00, so no gift can be left out of the columns.
 *
 * Two sets of figures are built: the gifts recorded at the service, and the gifts given on the service's day with no
 * service (mobile money and card gifts, and cash nobody attached). The second set belongs to the day's first service
 * only, so a day with two services does not count it twice (D-048). "The day" is the church's own day in its timezone.
 */
class OfferingReportBuilder
{
    /**
     * Takes the repositories.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly FundTypeRepositoryInterface $fundTypes,
        private readonly ServiceRepositoryInterface $services,
    ) {}

    /**
     * The report for this service.
     */
    public function build(Service $service): OfferingReport
    {
        $totals = $this->section($this->contributions->offeringTotalsForService($service->id), $this->contributions->anonymousCountForService($service->id));

        $countedHere = $this->services->firstOnDate($service->service_date)?->id === $service->id;

        return new OfferingReport($service, $totals, $countedHere ? $this->noServiceSection($service) : $this->section([], 0), $countedHere);
    }

    /**
     * The figures for the gifts given on the service's day with no service.
     */
    private function noServiceSection(Service $service): OfferingSection
    {
        $timezone = ($service->church ?? throw new LogicException('A service always belongs to a church.'))->timezone;
        $day = CarbonImmutable::parse($service->service_date->toDateString(), $timezone);
        [$from, $to] = [$day->startOfDay()->utc(), $day->endOfDay()->utc()];

        return $this->section($this->contributions->offeringTotalsWithoutService($from, $to), $this->contributions->anonymousCountWithoutService($from, $to));
    }

    /**
     * Arranges the lines the database gave into totals.
     *
     * @param  array<int, array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}>  $lines
     */
    private function section(array $lines, int $anonymous): OfferingSection
    {
        $methods = array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases());
        $byMethod = array_fill_keys($methods, '0.00');
        $perFund = [];
        $transactions = 0;
        $grand = '0.00';

        foreach ($lines as $line) {
            $perFund[$line['fund_type_id']][$line['fund_name']][$line['payment_method']] = $line['total'];
            $byMethod[$line['payment_method']] = bcadd($byMethod[$line['payment_method']] ?? '0.00', $line['total'], 2);
            $grand = bcadd($grand, $line['total'], 2);
            $transactions += $line['gifts'];
        }

        return new OfferingSection($grand, $this->fundLines($perFund, $methods), $byMethod, $transactions, $anonymous);
    }

    /**
     * One line for each fund and the name it had when its gifts were recorded, in the funds' display order. A fund
     * renamed between two gifts of one service shows as two lines, each under the name its gifts were given to.
     *
     * @param  array<string, array<string, array<string, numeric-string>>>  $perFund  by fund id, then fund name, then payment method
     * @param  list<string>  $methods
     * @return array<int, OfferingFund>
     */
    private function fundLines(array $perFund, array $methods): array
    {
        $lines = [];

        foreach ($this->fundTypes->findMany(array_keys($perFund)) as $fund) {
            $names = array_keys($perFund[$fund->id]);
            sort($names);

            foreach ($names as $name) {
                $amounts = array_merge(array_fill_keys($methods, '0.00'), $perFund[$fund->id][$name]);
                $lines[] = new OfferingFund($name, $amounts, array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00'));
            }
        }

        return $lines;
    }
}
