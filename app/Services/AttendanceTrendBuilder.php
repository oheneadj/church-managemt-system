<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Reports\AttendanceTrend;
use App\DTOs\Reports\AttendanceTrendDTO;
use App\Enums\ReportGrouping;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use App\Repositories\VisitorRecordRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Works out the attendance trend (docs/08, Attendance Trends; QA-11). The weeks are ISO weeks (Monday to Sunday) counted in
 * the church's own time zone, ending with the current week. A service counts once something was recorded for it: its check-ins,
 * or a paper headcount that stands in for them (docs/08, Update Service). A service nobody recorded, or one still to come, is
 * left out, so a week with no service is shown as 0 and does not drag the average down.
 */
class AttendanceTrendBuilder
{
    /**
     * Takes the service and visitor repositories.
     */
    public function __construct(
        private readonly ServiceRepositoryInterface $services,
        private readonly VisitorRecordRepositoryInterface $visitors,
    ) {}

    /**
     * Builds the report for the request.
     */
    public function build(AttendanceTrendDTO $dto): AttendanceTrend
    {
        $today = CarbonImmutable::now($dto->church->timezone)->startOfDay();
        $thisWeek = $today->startOfWeek(CarbonInterface::MONDAY);
        $firstWeek = $thisWeek->subWeeks($dto->weeks - 1);
        $held = $this->services->heldBetween($firstWeek, $today);

        $rows = $dto->groupBy === ReportGrouping::Week
            ? $this->weekRows($held, $thisWeek, $dto->weeks, $this->visitors->firstVisitDatesBetween($firstWeek, $thisWeek->addDays(6)))
            : $this->serviceRows($held);

        $totals = $this->totalsToSummarise($dto->groupBy, $rows);

        return new AttendanceTrend(
            $dto->groupBy,
            $rows,
            $totals === [] ? 0 : (int) round(array_sum($totals) / count($totals)),
            $totals === [] ? 0 : max($totals),
            $totals === [] ? 0 : min($totals),
        );
    }

    /**
     * One row for each week, newest first: the people who came, and the visitors who came for the first time.
     *
     * @param  iterable<Service>  $held
     * @param  list<string>  $firstVisitDates
     * @return list<array<string, int|string>>
     */
    private function weekRows(iterable $held, CarbonImmutable $thisWeek, int $weeks, array $firstVisitDates): array
    {
        $rows = [];

        for ($back = 0; $back < $weeks; $back++) {
            $start = $thisWeek->subWeeks($back);
            $from = $start->toDateString();
            $to = $start->addDays(6)->toDateString();
            $total = 0;
            $services = 0;

            foreach ($held as $service) {
                $date = $service->service_date->toDateString();

                if ($date >= $from && $date <= $to) {
                    $total += $this->totalOf($service);
                    $services++;
                }
            }

            $rows[] = [
                'week' => $start->format('o-\WW'),
                'week_start' => $from,
                'total' => $total,
                'first_timers' => count(array_filter($firstVisitDates, fn (string $date): bool => $date >= $from && $date <= $to)),
                'services' => $services,
            ];
        }

        return $rows;
    }

    /**
     * One row for each service, newest first.
     *
     * @param  iterable<Service>  $held
     * @return list<array<string, int|string>>
     */
    private function serviceRows(iterable $held): array
    {
        $rows = [];

        foreach ($held as $service) {
            $rows[] = [
                'service_id' => $service->id,
                'service_date' => $service->service_date->toDateString(),
                'service_name' => $service->name,
                'total' => $this->totalOf($service),
            ];
        }

        return $rows;
    }

    /**
     * The people at a service: the paper headcount if there is one, otherwise everyone checked in (members and visitors).
     */
    private function totalOf(Service $service): int
    {
        return $service->headcount_override ?? (int) $service->getAttribute('attendance_records_count');
    }

    /**
     * The totals the summary is worked out from: every service, or only the weeks in which a service was held.
     *
     * @param  list<array<string, int|string>>  $rows
     * @return list<int>
     */
    private function totalsToSummarise(ReportGrouping $groupBy, array $rows): array
    {
        $kept = $groupBy === ReportGrouping::Week ? array_filter($rows, fn (array $row): bool => $row['services'] > 0) : $rows;

        return array_values(array_map(fn (array $row): int => (int) $row['total'], $kept));
    }
}
