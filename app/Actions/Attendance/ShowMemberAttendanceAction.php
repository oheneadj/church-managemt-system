<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\MemberAttendance;
use App\DTOs\Attendance\ShowMemberAttendanceDTO;
use App\Repositories\AttendanceRecordRepositoryInterface;

/**
 * Shows one member's attendance over the last few weeks, with the services they were at and how many were held.
 */
final class ShowMemberAttendanceAction
{
    /**
     * Takes the attendance repository.
     */
    public function __construct(private readonly AttendanceRecordRepositoryInterface $attendance) {}

    /**
     * Loads the member's records and the number of services held in the window.
     */
    public function execute(ShowMemberAttendanceDTO $dto): MemberAttendance
    {
        $since = now()->subWeeks($dto->weeks);

        return new MemberAttendance($dto->member, $dto->weeks, $this->attendance->countServicesHeldSince($since), $this->attendance->recordsForMemberSince($dto->member, $since));
    }
}
