<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MemberDepartment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's place in a department (docs/08, Add / Remove Member from Department).
 *
 * @mixin MemberDepartment
 */
class DepartmentMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'department_id' => $this->department_id,
            'member_id' => $this->member_id,
            'role' => $this->role,
            'joined_at' => $this->joined_at?->toDateString(),
        ];
    }
}
