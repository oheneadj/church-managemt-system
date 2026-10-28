<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Contribution;
use App\Models\Member;

/**
 * The totals shown on a member's profile: what they gave and how often they came.
 * Money stays a decimal string (never a float) and voided contributions never count.
 */
class MemberSummaries
{
    /**
     * @return array{this_year: string, this_month: string, all_time: string}
     */
    public function giving(Member $member): array
    {
        $given = fn () => Contribution::query()->where('member_id', $member->getKey())->where('is_voided', false);

        return [
            'this_year' => $this->money($given()->where('contributed_at', '>=', now()->startOfYear())->sum('amount')),
            'this_month' => $this->money($given()->where('contributed_at', '>=', now()->startOfMonth())->sum('amount')),
            'all_time' => $this->money($given()->sum('amount')),
        ];
    }

    /**
     * @return array{last_4_weeks: int, last_8_weeks: int, last_12_weeks: int}
     */
    public function attendance(Member $member): array
    {
        $since = fn (int $weeks) => AttendanceRecord::query()
            ->where('member_id', $member->getKey())
            ->where('checked_in_at', '>=', now()->subWeeks($weeks))
            ->count();

        return ['last_4_weeks' => $since(4), 'last_8_weeks' => $since(8), 'last_12_weeks' => $since(12)];
    }

    private function money(mixed $sum): string
    {
        $value = (string) $sum;

        return is_numeric($value) ? bcadd($value, '0', 2) : '0.00';
    }
}
