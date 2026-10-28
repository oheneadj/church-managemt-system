<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A department a member serves in, with the role they hold there.
 *
 * @mixin Department
 */
class MemberDepartmentResource extends JsonResource
{
    /**
     * Shapes the department, with the member's role taken from the link between them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->pivot?->getAttribute('role'),
        ];
    }
}
