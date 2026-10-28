<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Attendance\MemberAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One member's attendance over the last few weeks (docs/08, Get Member Attendance).
 *
 * @property MemberAttendance $resource
 */
class MemberAttendanceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the member's attendance report.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'weeks' => $report->weeks,
            'services_held' => $report->servicesHeld,
            'attended' => $report->records->count(),
            'attendance_rate' => $report->rate(),
            'records' => $report->records->map(fn ($record): array => [
                'service_id' => $record->service_id,
                'service_name' => $record->service->name,
                'service_date' => $record->service->service_date->toDateString(),
                'method' => $record->method->value,
                'method_label' => $record->method->label(),
                'checked_in_at' => $record->checked_in_at->toIso8601String(),
            ])->all(),
        ];
    }
}
