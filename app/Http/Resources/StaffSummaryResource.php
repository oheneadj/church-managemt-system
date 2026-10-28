<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff member shown inside another record, such as a member's assigned pastor: just who they are.
 *
 * @mixin User
 */
class StaffSummaryResource extends JsonResource
{
    /**
     * Shapes the staff member's id and name.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
