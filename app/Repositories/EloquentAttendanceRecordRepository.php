<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AttendanceRecord;
use DateTimeInterface;

/**
 * Eloquent implementation of the attendance counts.
 */
class EloquentAttendanceRecordRepository implements AttendanceRecordRepositoryInterface
{
    public function countForMemberSince(string $memberId, DateTimeInterface $since): int
    {
        return AttendanceRecord::query()->where('member_id', $memberId)->where('checked_in_at', '>=', $since)->count();
    }
}
