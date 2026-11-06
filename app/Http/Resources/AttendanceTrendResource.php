<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Reports\AttendanceTrend;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The attendance trend report (docs/08, Attendance Trends). A week row is `week`, `week_start`, `total`, `first_timers` and
 * `services` (how many services were counted); a service row is `service_id`, `service_date`, `service_name` and `total`.
 * The summary covers only the weeks or services in which something was recorded.
 *
 * @property AttendanceTrend $resource
 */
class AttendanceTrendResource extends JsonResource
{
    /** The report has `data` and `summary` side by side, so it is not wrapped in another `data`. */
    public static $wrap = null;

    /**
     * Shapes the report for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'group_by' => $report->groupBy->value,
            'data' => $report->rows,
            'summary' => ['average' => $report->average, 'highest' => $report->highest, 'lowest' => $report->lowest],
        ];
    }
}
