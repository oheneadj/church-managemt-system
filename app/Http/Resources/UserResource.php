<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in staff member, with the roles and permissions the SPA uses to show or hide screens.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Shapes the user for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            /** @var list<string> */
            'roles' => $this->getRoleNames()->values(),
            /** @var list<string> */
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
