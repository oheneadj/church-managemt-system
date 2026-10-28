<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A department (docs/08, Departments).
 *
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * Shapes the department for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'head_user_id' => $this->head_user_id,
            'is_active' => $this->is_active,
            'members_count' => $this->whenCounted('members'),
        ];
    }
}
