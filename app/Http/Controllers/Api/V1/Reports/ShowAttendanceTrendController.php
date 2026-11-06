<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Actions\Reports\ShowAttendanceTrendAction;
use App\DTOs\Reports\AttendanceTrendDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AttendanceTrendRequest;
use App\Http\Resources\AttendanceTrendResource;

/**
 * The attendance trend, by week or by service (docs/08, Attendance Trends).
 */
class ShowAttendanceTrendController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ShowAttendanceTrendAction $action) {}

    /**
     * Runs ShowAttendanceTrendAction for this request and returns the AttendanceTrendResource.
     */
    public function __invoke(AttendanceTrendRequest $request): AttendanceTrendResource
    {
        return new AttendanceTrendResource($this->action->execute(AttendanceTrendDTO::fromRequest($request)));
    }
}
