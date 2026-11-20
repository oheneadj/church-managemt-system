<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Reports\MemberGrowth;
use App\DTOs\Reports\MemberGrowthDTO;
use App\Enums\MemberStatus;
use App\Repositories\AuditLogRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Works out the member growth report (docs/08, Member Growth; docs/02; QA-11). It is read from the member change history
 * (D-030). A member is "new" in the month they became active (added as active, a prospect confirmed, or a member who came
 * back) and "lost" in the month they left active membership (to any other status, a move back to prospect included, so the
 * numbers add up to the active total). Months are calendar months in the church's own time zone, ending with the current one.
 *
 * The active total of this month is today's real count; each earlier month is worked back from the next by taking away what
 * that month added and putting back what it lost. A member whose data was erased no longer has readable history, so they are
 * not in the earlier months.
 */
class MemberGrowthBuilder
{
    /**
     * Takes the change history and member repositories.
     */
    public function __construct(
        private readonly AuditLogRepositoryInterface $history,
        private readonly MemberRepositoryInterface $members,
    ) {}

    /**
     * Builds the report for the request.
     */
    public function build(MemberGrowthDTO $dto): MemberGrowth
    {
        $timezone = $dto->church->timezone;
        $thisMonth = CarbonImmutable::now($timezone)->startOfMonth();
        $firstMonth = $thisMonth->subMonthsNoOverflow($dto->months - 1);
        $new = [];
        $lost = [];

        foreach ($this->history->memberStatusChangesBetween($firstMonth->utc(), $thisMonth->endOfMonth()->utc()) as $change) {
            $month = $change->at->setTimezone($timezone)->format('Y-m');

            if ($change->to === MemberStatus::Active && $change->from !== MemberStatus::Active) {
                $new[$month] = ($new[$month] ?? 0) + 1;
            } elseif ($change->from === MemberStatus::Active && $change->to !== MemberStatus::Active) {
                $lost[$month] = ($lost[$month] ?? 0) + 1;
            }
        }

        $rows = [];
        $active = $this->members->countActive();

        for ($back = 0; $back < $dto->months; $back++) {
            $start = $thisMonth->subMonthsNoOverflow($back);
            $key = $start->format('Y-m');
            $net = ($new[$key] ?? 0) - ($lost[$key] ?? 0);

            $rows[] = ['month' => $key, 'month_start' => $start->toDateString(), 'new' => $new[$key] ?? 0, 'lost' => $lost[$key] ?? 0, 'net' => $net, 'active_total' => $active];
            $active -= $net;
        }

        return new MemberGrowth($rows, array_sum(array_column($rows, 'new')), array_sum(array_column($rows, 'lost')), array_sum(array_column($rows, 'net')));
    }
}
