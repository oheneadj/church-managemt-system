<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Enums\CheckInMethod;

/**
 * The headcount of one service: everyone, members and visitors apart, how each person checked in, and what share
 * of the active members came. The rate is a whole percent, worked out with integers.
 */
final readonly class AttendanceSummary
{
    /**
     * Holds the counts.
     *
     * @param  array<string, int>  $byMethod  a count for every check-in method, zero when nobody used it
     */
    public function __construct(
        public int $total,
        public int $members,
        public int $visitors,
        public array $byMethod,
        public int $activeMembers,
    ) {}

    /**
     * The share of active members who were checked in, as a whole percent. Zero when the church has no active members.
     */
    public function attendanceRate(): int
    {
        return $this->activeMembers === 0 ? 0 : intdiv($this->members * 100 + intdiv($this->activeMembers, 2), $this->activeMembers);
    }

    /**
     * A count of zero for every method, to fill in from the database.
     *
     * @return array<string, int>
     */
    public static function emptyByMethod(): array
    {
        return array_fill_keys(array_map(fn (CheckInMethod $method): string => $method->value, CheckInMethod::cases()), 0);
    }
}
