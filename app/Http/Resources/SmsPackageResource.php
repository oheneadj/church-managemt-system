<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SmsPackage;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An SMS credit package: the bundle's name, its credits and its price, as an exact decimal string with a formatted sibling
 * (agreement section 12).
 *
 * @mixin SmsPackage
 */
class SmsPackageResource extends JsonResource
{
    /**
     * Shapes the package for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'credits' => $this->credits,
            'price' => $this->price,
            'price_formatted' => Money::ghs($this->price)->formatted(),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
