<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Attendance\AttendanceSummary;
use App\DTOs\Attendance\ListServiceAttendanceDTO;
use App\Enums\CheckInMethod;
use App\Models\AttendanceRecord;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorRecord;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The attendance counts the member profile shows.
 */
interface AttendanceRecordRepositoryInterface
{
    /**
     * How many services the member was recorded at since the given moment.
     */
    public function countForMemberSince(string $memberId, DateTimeInterface $since): int;

    /**
     * Records these members at the service, skipping anyone already recorded, and returns how many rows were added.
     * It is one statement that ignores a clash on the unique index, so two check-ins at once leave one row.
     *
     * @param  list<string>  $memberIds
     */
    public function insertMembers(Service $service, array $memberIds, CheckInMethod $method, CarbonInterface $at): int;

    /**
     * Records a visitor at the service unless they are already recorded there. Returns whether a row was added.
     */
    public function insertVisitor(Service $service, VisitorRecord $visitor, CarbonInterface $at): bool;

    /**
     * The headcount of a service: everyone, members, visitors and a count for each check-in method.
     */
    public function summaryFor(Service $service, int $activeMembers): AttendanceSummary;

    /**
     * One page of the people checked in to a service, newest first. A viewer who may not see visitors gets members only,
     * and only the members they are allowed to see.
     *
     * @return LengthAwarePaginator<int, AttendanceRecord>
     */
    public function paginateForService(ListServiceAttendanceDTO $filters, bool $canSeeVisitors): LengthAwarePaginator;

    /**
     * The member's attendance records since the date, with their services, oldest first.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function recordsForMemberSince(Member $member, CarbonInterface $since): Collection;

    /**
     * How many services were held since the date. A service counts as held when someone was checked in to it.
     */
    public function countServicesHeldSince(CarbonInterface $since): int;

    /**
     * Active members who missed each of the last `$consecutive` held services of the given kinds, newest absence first.
     * A member who joined after the oldest of those services is left out, because they could not have been there.
     *
     * @param  list<string>  $serviceTypes
     * @return LengthAwarePaginator<int, Member>
     */
    public function absentMembers(User $viewer, array $serviceTypes, int $consecutive, int $perPage): LengthAwarePaginator;
}
