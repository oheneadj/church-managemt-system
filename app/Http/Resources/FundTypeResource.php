<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FundType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fund type (docs/08, Fund Types).
 *
 * @mixin FundType
 */
class FundTypeResource extends JsonResource
{
    /**
     * Shapes the fund type for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
