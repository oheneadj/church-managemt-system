<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The church's own details (docs/08, Church Profile). Payout, billing and credit details are deliberately left out.
 *
 * @mixin Church
 */
class ChurchProfileResource extends JsonResource
{
    /**
     * Shapes the church's details for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'denomination' => $this->denomination,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'phone' => $this->phone,
            'email' => $this->email,
            'timezone' => $this->timezone,
        ];
    }
}
