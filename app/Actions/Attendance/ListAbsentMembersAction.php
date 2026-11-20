<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\ListAbsentMembersDTO;
use App\Models\Member;
use App\Repositories\AttendanceRecordRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists the active members who missed the last few services in a row (QA-05 TC-05-12). It is worked out when asked, so
 * it is always up to date and nothing has to be stored or cleared.
 */
final class ListAbsentMembersAction
{
    /**
     * Takes the attendance repository.
     */
    public function __construct(private readonly AttendanceRecordRepositoryInterface $attendance) {}

    /**
     * Lists one page of absent members that the viewer may see.
     *
     * @return LengthAwarePaginator<int, Member>
     */
    public function execute(ListAbsentMembersDTO $dto): LengthAwarePaginator
    {
        return $this->attendance->absentMembers($dto->viewer, config('attendance.absence_service_types'), $dto->consecutive, $dto->perPage);
    }
}
