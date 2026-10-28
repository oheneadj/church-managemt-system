<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\ListServiceAttendanceDTO;
use App\DTOs\Attendance\ServiceAttendance;
use App\Enums\StaffPermission;
use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;

/**
 * Shows who checked in to a service: the headcount and one page of people. Visitors' names are shown only to staff who
 * may edit members, and everyone else sees only the members they are allowed to see.
 */
final class ListServiceAttendanceAction
{
    /**
     * Takes the attendance and member repositories.
     */
    public function __construct(
        private readonly AttendanceRecordRepositoryInterface $attendance,
        private readonly MemberRepositoryInterface $members,
    ) {}

    /**
     * Builds the headcount and loads one page of records.
     */
    public function execute(ListServiceAttendanceDTO $dto): ServiceAttendance
    {
        $canSeeVisitors = $dto->viewer->can(StaffPermission::MembersEdit->value);

        return new ServiceAttendance(
            $dto->service,
            $this->attendance->summaryFor($dto->service, $this->members->countActive()),
            $this->attendance->paginateForService($dto, $canSeeVisitors),
        );
    }
}
