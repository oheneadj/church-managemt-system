<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Attendance\AttendanceRecorded;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a bulk check-in did (docs/08, Record Attendance).
 *
 * @property AttendanceRecorded $resource
 */
class AttendanceRecordedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the result of a bulk check-in.
     *
     * @return array{recorded: int, already_checked_in: int, errors: list<array{member_id: string, message: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'recorded' => $this->resource->recorded,
            'already_checked_in' => $this->resource->alreadyCheckedIn,
            'errors' => $this->resource->errors,
        ];
    }
}
