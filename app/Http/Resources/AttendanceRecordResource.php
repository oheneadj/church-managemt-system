<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person checked in to a service: a member, or a walk-in visitor by name (docs/08, Get Attendance for a Service).
 *
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * Shapes the attendance record for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'checked_in_at' => $this->checked_in_at->toIso8601String(),
            'member' => $this->member === null ? null : [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'full_name' => $this->member->full_name,
            ],
            'visitor_name' => $this->visitor_name,
        ];
    }
}
