<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Http\Requests\Attendance\ShowMemberAttendanceRequest;
use App\Models\Member;

/**
 * A request for one member's attendance over the last few weeks.
 */
final readonly class ShowMemberAttendanceDTO
{
    /**
     * Holds the member and the number of weeks to look back.
     */
    public function __construct(public Member $member, public int $weeks) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ShowMemberAttendanceRequest $request): self
    {
        return new self($request->member(), (int) ($request->validated('weeks') ?? config('attendance.member_report_weeks')));
    }
}
