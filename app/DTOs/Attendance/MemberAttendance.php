<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * One member's attendance over a number of weeks: the services held, how many they were at, and where.
 */
final readonly class MemberAttendance
{
    /**
     * Holds the member, the window and the counts.
     *
     * @param  Collection<int, AttendanceRecord>  $records
     */
    public function __construct(public Member $member, public int $weeks, public int $servicesHeld, public Collection $records) {}

    /**
     * The share of held services the member was at, as a whole percent. Zero when no service was held.
     */
    public function rate(): int
    {
        return $this->servicesHeld === 0 ? 0 : intdiv($this->records->count() * 100 + intdiv($this->servicesHeld, 2), $this->servicesHeld);
    }
}
