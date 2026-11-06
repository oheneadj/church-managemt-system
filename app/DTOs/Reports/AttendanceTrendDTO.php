<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\ReportGrouping;
use App\Http\Requests\Reports\AttendanceTrendRequest;
use App\Models\Church;
use LogicException;

/**
 * What the attendance trend is asked for: the church, how many weeks back and how to group the rows.
 */
final readonly class AttendanceTrendDTO
{
    use ResolvesActor;

    /**
     * Holds the church (for its time zone), the number of weeks and the grouping.
     */
    public function __construct(public Church $church, public int $weeks, public ReportGrouping $groupBy) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(AttendanceTrendRequest $request): self
    {
        $v = $request->validated();

        return new self(
            self::actorFrom($request)->church ?? throw new LogicException('A staff member always belongs to a church.'),
            (int) ($v['weeks'] ?? config('reports.attendance_weeks_default')),
            ReportGrouping::from($v['group_by'] ?? config('reports.attendance_group_by_default')),
        );
    }
}
