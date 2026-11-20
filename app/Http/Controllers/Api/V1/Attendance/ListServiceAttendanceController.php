<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\ListServiceAttendanceAction;
use App\DTOs\Attendance\ListServiceAttendanceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ListServiceAttendanceRequest;
use App\Http\Resources\AttendanceCollection;

/**
 * Who checked in to a service, with its headcount (docs/08, Get Attendance for a Service).
 */
class ListServiceAttendanceController extends Controller
{
    /**
     * Takes the list service attendance action.
     */
    public function __construct(private readonly ListServiceAttendanceAction $action) {}

    /**
     * Runs ListServiceAttendanceAction for this request and returns the AttendanceCollection.
     */
    public function __invoke(ListServiceAttendanceRequest $request): AttendanceCollection
    {
        return new AttendanceCollection($this->action->execute(ListServiceAttendanceDTO::fromRequest($request)));
    }
}
