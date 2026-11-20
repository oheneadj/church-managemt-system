<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\ShowMemberAttendanceAction;
use App\DTOs\Attendance\ShowMemberAttendanceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ShowMemberAttendanceRequest;
use App\Http\Resources\MemberAttendanceResource;

/**
 * One member's attendance over the last few weeks (docs/08, Get Member Attendance).
 */
class ShowMemberAttendanceController extends Controller
{
    /**
     * Takes the show member attendance action.
     */
    public function __construct(private readonly ShowMemberAttendanceAction $action) {}

    /**
     * Runs ShowMemberAttendanceAction for this request and returns the MemberAttendanceResource.
     */
    public function __invoke(ShowMemberAttendanceRequest $request): MemberAttendanceResource
    {
        return new MemberAttendanceResource($this->action->execute(ShowMemberAttendanceDTO::fromRequest($request)));
    }
}
