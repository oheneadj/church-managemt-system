<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SmsPackage;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An SMS credit package as a church sees it when buying: the name, the credits and the price, as an exact decimal string with a
 * formatted sibling. Nothing about the owner's switches or ordering.
 *
 * @mixin SmsPackage
 */
class SmsCreditPackageResource extends JsonResource
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
        ];
    }
}
