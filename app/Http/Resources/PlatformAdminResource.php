<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform admin as they see themselves. The password is never part of it.
 *
 * @mixin PlatformAdmin
 */
class PlatformAdminResource extends JsonResource
{
    /**
     * Shapes the platform admin for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}
