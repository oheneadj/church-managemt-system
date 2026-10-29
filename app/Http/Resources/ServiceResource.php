<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service, with its attendance count when it is loaded (docs/08, Services & Attendance).
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * Shapes the service for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'checkin_code' => $this->checkin_code,
            'schedule_id' => $this->service_schedule_id,
            'service_date' => $this->service_date->toDateString(),
            'starts_at' => $this->starts_at === null ? null : substr((string) $this->starts_at, 0, 5),
            'ends_at' => $this->ends_at === null ? null : substr((string) $this->ends_at, 0, 5),
            'is_open' => $this->is_open,
            'headcount_override' => $this->headcount_override,
            'notes' => $this->notes,
            'attendance_count' => $this->whenCounted('attendanceRecords'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
