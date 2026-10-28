<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member as one row of the directory (docs/08, List Members).
 *
 * @mixin Member
 */
class MemberSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'full_name' => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"),
            'preferred_name' => $this->preferred_name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'status' => $this->status,
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->pluck('name')->values()),
            'family_id' => $this->family_id,
            'membership_date' => $this->membership_date?->toDateString(),
        ];
    }
}
