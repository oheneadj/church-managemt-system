<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ServiceSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A weekly schedule (docs/08, Service Schedules). Times are `H:i`, as on a service.
 *
 * @mixin ServiceSchedule
 */
class ServiceScheduleResource extends JsonResource
{
    /**
     * Shapes the schedule for the API.
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
            'weekday' => $this->weekday->value,
            'weekday_label' => $this->weekday->label(),
            'starts_at' => $this->starts_at === null ? null : substr($this->starts_at, 0, 5),
            'ends_at' => $this->ends_at === null ? null : substr($this->ends_at, 0, 5),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
