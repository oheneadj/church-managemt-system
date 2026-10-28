<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A family, with its head and members when they are loaded (docs/08, Families).
 *
 * @mixin Family
 */
class FamilyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'head_member_id' => $this->head_member_id,
            'members_count' => $this->whenCounted('members'),
            'members' => $this->whenLoaded('members', fn () => FamilyMemberResource::collection($this->members)),
        ];
    }
}
