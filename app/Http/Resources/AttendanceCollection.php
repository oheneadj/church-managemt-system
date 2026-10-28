<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Attendance\ServiceAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * One page of the people checked in to a service, with the service and its headcount beside the page
 * (docs/08, Get Attendance for a Service). The headcount covers everyone, not just this page.
 */
class AttendanceCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = AttendanceRecordResource::class;

    /**
     * Takes the attendance of a service: its records are the page, the rest is shown beside them.
     */
    public function __construct(private readonly ServiceAttendance $attendance)
    {
        parent::__construct($attendance->records);
    }

    /**
     * The service and its headcount, added beside `data` and `meta`.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $summary = $this->attendance->summary;

        return [
            'service' => new ServiceResource($this->attendance->service),
            'summary' => [
                'total' => $summary->total,
                'members' => $summary->members,
                'visitors' => $summary->visitors,
                'by_method' => $summary->byMethod,
                'active_members' => $summary->activeMembers,
                'attendance_rate' => $summary->attendanceRate(),
            ],
        ];
    }
}
