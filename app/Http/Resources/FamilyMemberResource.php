<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member as listed inside a family. It carries no contact details.
 *
 * @mixin Member
 */
class FamilyMemberResource extends JsonResource
{
    /**
     * Shapes the member's identity, status and role in the family.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'full_name' => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"),
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'family_role' => $this->family_role?->value,
            'family_role_label' => $this->family_role?->label(),
        ];
    }
}
