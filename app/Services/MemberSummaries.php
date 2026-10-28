<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\ContributionRepositoryInterface;

/**
 * The totals shown on a member's profile: what they gave and how often they came.
 * Money stays a decimal string (never a float) and voided contributions never count.
 */
class MemberSummaries
{
    /**
     * Takes the contribution repository and the attendance record repository.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly AttendanceRecordRepositoryInterface $attendance,
    ) {}

    /**
     * The member's giving this year, this month and all time, as exact decimal strings.
     *
     * @return array{this_year: string, this_month: string, all_time: string}
     */
    public function giving(Member $member): array
    {
        return [
            'this_year' => $this->contributions->totalForMember($member->getKey(), now()->startOfYear()),
            'this_month' => $this->contributions->totalForMember($member->getKey(), now()->startOfMonth()),
            'all_time' => $this->contributions->totalForMember($member->getKey(), null),
        ];
    }

    /**
     * How many services the member attended in the last 4, 8 and 12 weeks.
     *
     * @return array{last_4_weeks: int, last_8_weeks: int, last_12_weeks: int}
     */
    public function attendance(Member $member): array
    {
        return [
            'last_4_weeks' => $this->attendance->countForMemberSince($member->getKey(), now()->subWeeks(4)),
            'last_8_weeks' => $this->attendance->countForMemberSince($member->getKey(), now()->subWeeks(8)),
            'last_12_weeks' => $this->attendance->countForMemberSince($member->getKey(), now()->subWeeks(12)),
        ];
    }
}
