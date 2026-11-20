<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Attendance\AttendanceSummary;
use App\DTOs\Attendance\ListServiceAttendanceDTO;
use App\Enums\CheckInMethod;
use App\Enums\MemberStatus;
use App\Enums\MessageStatus;
use App\Models\AttendanceRecord;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorRecord;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Eloquent implementation of the attendance counts.
 */
class EloquentAttendanceRecordRepository implements AttendanceRecordRepositoryInterface
{
    /**
     * How many services the member was recorded at since the given moment.
     */
    public function countForMemberSince(string $memberId, DateTimeInterface $since): int
    {
        return AttendanceRecord::query()->where('member_id', $memberId)->where('checked_in_at', '>=', $since)->count();
    }

    /**
     * Records these members at the service, skipping anyone already recorded, and returns how many rows were added.
     * It is one statement that ignores a clash on the unique index, so two check-ins at once leave one row.
     *
     * @param  list<string>  $memberIds
     */
    public function insertMembers(Service $service, array $memberIds, CheckInMethod $method, CarbonInterface $at): int
    {
        $rows = array_map(fn (string $memberId): array => [
            'id' => (string) Str::ulid(),
            'church_id' => $service->church_id,
            'service_id' => $service->id,
            'member_id' => $memberId,
            'method' => $method->value,
            'checked_in_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ], $memberIds);

        return $rows === [] ? 0 : AttendanceRecord::query()->insertOrIgnore($rows);
    }

    /**
     * Records a visitor at the service unless they are already recorded there. Returns whether a row was added.
     */
    public function insertVisitor(Service $service, VisitorRecord $visitor, CarbonInterface $at): bool
    {
        return AttendanceRecord::query()->insertOrIgnore([[
            'id' => (string) Str::ulid(),
            'church_id' => $service->church_id,
            'service_id' => $service->id,
            'visitor_record_id' => $visitor->id,
            'visitor_name' => $visitor->name,
            'method' => CheckInMethod::Manual->value,
            'checked_in_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]]) === 1;
    }

    /**
     * The headcount of a service: everyone, members, visitors and a count for each check-in method.
     */
    public function summaryFor(Service $service, int $activeMembers): AttendanceSummary
    {
        $byMethod = AttendanceRecord::query()->where('service_id', $service->id)->selectRaw('method, count(*) as total')->groupBy('method')->pluck('total', 'method');
        $members = AttendanceRecord::query()->where('service_id', $service->id)->whereNotNull('member_id')->count();

        return new AttendanceSummary(
            total: (int) $byMethod->sum(),
            members: $members,
            visitors: (int) $byMethod->sum() - $members,
            byMethod: array_map('intval', [...AttendanceSummary::emptyByMethod(), ...$byMethod->all()]),
            activeMembers: $activeMembers,
        );
    }

    /**
     * One page of the people checked in to a service, newest first. A viewer who may not see visitors gets members only,
     * and only the members they are allowed to see.
     *
     * @return LengthAwarePaginator<int, AttendanceRecord>
     */
    public function paginateForService(ListServiceAttendanceDTO $filters, bool $canSeeVisitors): LengthAwarePaginator
    {
        return AttendanceRecord::query()
            ->where('service_id', $filters->service->id)
            ->with('member')
            ->when(! $canSeeVisitors, fn (Builder $q) => $q->whereHas('member', fn (Builder $member) => $member->visibleTo($filters->viewer)))
            ->orderByDesc('checked_in_at')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * The member's attendance records since the date, with their services, oldest first.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function recordsForMemberSince(Member $member, CarbonInterface $since): Collection
    {
        return AttendanceRecord::query()
            ->where('member_id', $member->id)
            ->whereHas('service', fn (Builder $service) => $service->whereDate('service_date', '>=', $since))
            ->with('service')
            ->orderBy('checked_in_at')
            ->get();
    }

    /**
     * How many services were held since the date. A service counts as held when someone was checked in to it.
     */
    public function countServicesHeldSince(CarbonInterface $since): int
    {
        return Service::query()->whereDate('service_date', '>=', $since)->whereDate('service_date', '<=', today())->whereHas('attendanceRecords')->count();
    }

    /**
     * Active members who missed each of the last `$consecutive` held services of the given kinds, newest absence first.
     * A member who joined after the oldest of those services is left out, because they could not have been there.
     *
     * @param  list<string>  $serviceTypes
     * @return LengthAwarePaginator<int, Member>
     */
    public function absentMembers(User $viewer, array $serviceTypes, int $consecutive, int $perPage): LengthAwarePaginator
    {
        return ($this->absentQuery($serviceTypes, $consecutive) ?? Member::query()->whereRaw('1 = 0'))
            ->visibleTo($viewer)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * The absent members who are due the automatic text: the ones who may be texted and have not already had one for this
     * stretch of absence. A text that failed for good does not count, so the member is tried again. A member who has been
     * back since their last text is due again once they miss the same number of services again.
     *
     * @param  list<string>  $serviceTypes
     * @return Collection<int, Member>
     */
    public function absentMembersToAlert(array $serviceTypes, int $consecutive): Collection
    {
        $absent = $this->absentQuery($serviceTypes, $consecutive);

        if ($absent === null) {
            return new Collection;
        }

        return $absent
            ->where('sms_opted_in', true)
            ->where('phone', '!=', '')
            ->withMax(['attendanceAlerts as last_alert_at' => fn (Builder $logs) => $logs->where('status', '!=', MessageStatus::Failed->value)], 'created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Member $member): bool => $this->isDueAlert($member))
            ->values();
    }

    /**
     * Whether the member has had no text since they were last at church (or has never been).
     */
    private function isDueAlert(Member $member): bool
    {
        $alertedAt = $member->getAttribute('last_alert_at');

        if ($alertedAt === null) {
            return true;
        }

        return $member->last_attended_at !== null && Carbon::parse((string) $alertedAt)->lt(Carbon::parse($member->last_attended_at));
    }

    /**
     * The active members who missed each of the last `$consecutive` held services of the given kinds, with the time of their
     * last check-in, or null when there are not yet that many services to judge by. The list and the automatic text share it,
     * so the text goes to the people the list shows.
     *
     * @param  list<string>  $serviceTypes
     * @return Builder<Member>|null
     */
    private function absentQuery(array $serviceTypes, int $consecutive): ?Builder
    {
        $recent = Service::query()
            ->whereIn('type', $serviceTypes)
            ->whereDate('service_date', '<=', today())
            ->whereHas('attendanceRecords')
            ->orderByDesc('service_date')
            ->limit($consecutive)
            ->get(['id', 'service_date']);

        $oldest = $recent->last();

        if ($oldest === null || $recent->count() < $consecutive) {
            // Not enough history yet to say anyone missed that many in a row.
            return null;
        }

        return Member::query()
            ->where('status', MemberStatus::Active)
            ->whereDate('created_at', '<=', $oldest->service_date)
            ->whereDoesntHave('attendanceRecords', fn (Builder $records) => $records->whereIn('service_id', $recent->pluck('id')))
            ->withMax('attendanceRecords as last_attended_at', 'checked_in_at');
    }
}
