<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A member who has missed the last few services in a row, with the last time they were checked in (QA-05 TC-05-12).
 *
 * @mixin Member
 */
class AbsentMemberResource extends JsonResource
{
    /**
     * Shapes the absent member for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'last_attended_at' => $this->last_attended_at === null ? null : Carbon::parse($this->last_attended_at)->toIso8601String(),
        ];
    }
}
