<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Auth\CurrentStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in staff member under "data", with their church beside it (docs/08, Get Authenticated User).
 *
 * @mixin CurrentStaff
 */
class CurrentStaffResource extends JsonResource
{
    /**
     * Shapes the current staff for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (new UserResource($this->user))->toArray($request);
    }

    /**
     * Adds the data that sits beside the main resource in the reply.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['church' => new ChurchResource($this->church)];
    }
}
