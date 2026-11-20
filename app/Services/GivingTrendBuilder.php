<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Reports\GivingTrend;
use App\DTOs\Reports\GivingTrendDTO;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\FundTypeRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Works out the giving trend (docs/08, Giving Trends; QA-11). Months are calendar months in the church's own time zone,
 * ending with the current one; gifts that were voided are never counted. All the arithmetic is exact (bcmath), because it is
 * money. The summary sets the whole period against the same number of months immediately before it.
 */
class GivingTrendBuilder
{
    /**
     * Takes the contribution and fund type repositories.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly FundTypeRepositoryInterface $fundTypes,
    ) {}

    /**
     * Builds the report for the request.
     */
    public function build(GivingTrendDTO $dto): GivingTrend
    {
        $thisMonth = CarbonImmutable::now($dto->church->timezone)->startOfMonth();
        $months = [];
        $total = '0.00';

        for ($back = 0; $back < $dto->months; $back++) {
            $start = $thisMonth->subMonthsNoOverflow($back);
            $perFund = $this->contributions->totalsByFundBetween($start->utc(), $start->endOfMonth()->utc(), $dto->fundTypeId);
            $monthTotal = $this->sum($perFund);
            $total = bcadd($total, $monthTotal, 2);

            $months[] = [
                'month' => $start->format('Y-m'),
                'month_start' => $start->toDateString(),
                'total' => $monthTotal,
                'by_fund' => $this->fundLines($perFund),
            ];
        }

        $firstOfPeriod = $thisMonth->subMonthsNoOverflow($dto->months - 1);
        $previousStart = $firstOfPeriod->subMonthsNoOverflow($dto->months);
        $previous = $this->sum($this->contributions->totalsByFundBetween($previousStart->utc(), $firstOfPeriod->subSecond()->utc(), $dto->fundTypeId));

        return new GivingTrend($months, $total, $previous, $this->changePercent($total, $previous));
    }

    /**
     * The fund totals of a month, one line for each fund in the funds' display order.
     *
     * @param  array<string, numeric-string>  $perFund  by fund id
     * @return list<array{fund_type_id: string, name: string, total: numeric-string}>
     */
    private function fundLines(array $perFund): array
    {
        $lines = [];

        foreach ($this->fundTypes->findMany(array_keys($perFund)) as $fund) {
            $lines[] = ['fund_type_id' => $fund->id, 'name' => $fund->name, 'total' => $perFund[$fund->id]];
        }

        return $lines;
    }

    /**
     * The sum of the fund totals.
     *
     * @param  array<string, numeric-string>  $perFund
     * @return numeric-string
     */
    private function sum(array $perFund): string
    {
        $sum = '0.00';

        foreach ($perFund as $amount) {
            $sum = bcadd($sum, $amount, 2);
        }

        return $sum;
    }

    /**
     * How much more (or less) was given than in the months before, as a percentage to one decimal place, or null when nothing
     * was given then. The division is exact; only the finished percentage is turned into a number.
     *
     * @param  numeric-string  $total
     * @param  numeric-string  $previous
     */
    private function changePercent(string $total, string $previous): ?float
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        return round((float) bcdiv(bcsub($total, $previous, 2), $previous, 6) * 100, 1);
    }
}
