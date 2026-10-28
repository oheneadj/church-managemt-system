<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's family and the role the member holds in it, as shown on the member record.
 *
 * @mixin Member
 */
class MemberFamilyResource extends JsonResource
{
    /**
     * Shapes the family the member belongs to, with the member's role in it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->family?->id,
            'name' => $this->family?->name,
            'role' => $this->family_role?->value,
            'role_label' => $this->family_role?->label(),
        ];
    }
}
