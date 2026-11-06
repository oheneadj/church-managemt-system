<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff member as the church admin sees them on the staff screen. The password is never part of it.
 *
 * @mixin User
 */
class StaffResource extends JsonResource
{
    /**
     * Shapes the staff member for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            /** @var list<string> */
            'roles' => $this->roles->pluck('name')->values(),
            /** @var list<string> permissions granted on top of the role */
            'extra_permissions' => $this->permissions->pluck('name')->values(),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
