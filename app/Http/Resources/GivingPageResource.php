<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Giving\GivingPage;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the public giving page shows (docs/08, The Public Giving Page). Only the church's name and its funds' ids and names: nothing
 * about its people, its money or its account.
 *
 * @mixin GivingPage
 */
class GivingPageResource extends JsonResource
{
    /**
     * Shapes the page for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'church_name' => $this->churchName,
            'available' => $this->available,
            'funds' => $this->funds,
            'min_gift' => $this->minGift,
            'min_gift_formatted' => Money::ghs($this->minGift)->formatted(),
            'max_gift' => $this->maxGift,
            'max_gift_formatted' => Money::ghs($this->maxGift)->formatted(),
            'processing_fee_percent' => $this->processingFeePercent,
        ];
    }
}
