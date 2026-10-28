<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The attendance of one service: the service, its headcount and one page of the people who checked in.
 */
final readonly class ServiceAttendance
{
    /**
     * Holds the service, the summary and the page of records.
     *
     * @param  LengthAwarePaginator<int, AttendanceRecord>  $records
     */
    public function __construct(public Service $service, public AttendanceSummary $summary, public LengthAwarePaginator $records) {}
}
