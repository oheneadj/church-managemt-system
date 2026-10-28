<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\RecordAttendanceAction;
use App\DTOs\Attendance\RecordAttendanceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Http\Resources\AttendanceRecordedResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks members present at a service by hand (docs/08, Record Attendance). It answers 201 even when everyone was already checked in; the counts say what happened.
 */
class RecordAttendanceController extends Controller
{
    /**
     * Takes the record attendance action.
     */
    public function __construct(private readonly RecordAttendanceAction $action) {}

    /**
     * Runs RecordAttendanceAction for this request and returns the AttendanceRecordedResource.
     */
    public function __invoke(RecordAttendanceRequest $request): JsonResponse
    {
        return (new AttendanceRecordedResource($this->action->execute(RecordAttendanceDTO::fromRequest($request))))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
