<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use App\Services\MemberPhotoService;
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
     * Shapes the member summary for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'full_name' => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"),
            'preferred_name' => $this->preferred_name,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'phone' => $this->phone,
            /** @var string|null */
            'photo_url' => app(MemberPhotoService::class)->url($this->photo_url),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->pluck('name')->values()),
            'family_id' => $this->family_id,
            'membership_date' => $this->membership_date?->toDateString(),
        ];
    }
}
