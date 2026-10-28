<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\AttendanceRecorded;
use App\DTOs\Attendance\RecordAttendanceDTO;
use App\Enums\CheckInMethod;
use App\Services\AttendanceRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Marks members present at a service by hand. It also works on a closed service, so a member who was there but was not
 * recorded can be added afterwards (QA-05 TC-05-14).
 */
final class RecordAttendanceAction
{
    /**
     * Takes the attendance recorder.
     */
    public function __construct(private readonly AttendanceRecorder $recorder) {}

    /**
     * Records the members and reports how many were new, how many were already in, and any that could not be checked in.
     */
    public function execute(RecordAttendanceDTO $dto): AttendanceRecorded
    {
        return DB::transaction(fn (): AttendanceRecorded => $this->recorder->recordMembers($dto->service, $dto->memberIds, CheckInMethod::Manual));
    }
}
