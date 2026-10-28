<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Attendance\AttendanceRecorded;
use App\Enums\CheckInMethod;
use App\Enums\MemberStatus;
use App\Models\Service;
use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;

/**
 * Checks members in to a service, however they arrived (by hand, by QR code, by WhatsApp). It works out who can be
 * checked in, records them in one statement that ignores anyone already recorded, and reports what happened to each.
 * It opens no transaction: the caller's does.
 */
class AttendanceRecorder
{
    /**
     * Takes the member and attendance repositories.
     */
    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly AttendanceRecordRepositoryInterface $attendance,
    ) {}

    /**
     * Records these members at the service. An id that is not a member of this church, or belongs to someone who has
     * died, is reported in `errors` and does not stop the others. Checking in twice is not an error: the second time
     * counts as already checked in.
     *
     * @param  list<string>  $memberIds
     */
    public function recordMembers(Service $service, array $memberIds, CheckInMethod $method): AttendanceRecorded
    {
        $found = $this->members->findMany($memberIds)->keyBy('id');
        $eligible = [];
        $errors = [];

        foreach ($memberIds as $memberId) {
            $member = $found->get($memberId);

            if ($member === null) {
                $errors[] = ['member_id' => $memberId, 'message' => 'Member not found.'];
            } elseif ($member->status === MemberStatus::Deceased) {
                $errors[] = ['member_id' => $memberId, 'message' => 'This member cannot be checked in.'];
            } else {
                $eligible[] = $memberId;
            }
        }

        $recorded = $this->attendance->insertMembers($service, $eligible, $method, now());

        return new AttendanceRecorded($recorded, count($eligible) - $recorded, $errors);
    }
}
