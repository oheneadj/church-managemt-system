<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Giving\PublicGift;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gift started on the public giving page (docs/08, The Public Giving Page). The giver is shown the reference, the amounts and
 * whether this is a payment they already had waiting. Nothing about the church's records or the giver is sent back.
 *
 * @mixin PublicGift
 */
class PublicGiftResource extends JsonResource
{
    /**
     * Shapes the started gift for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->gift->id,
            'status' => $this->gift->status->value,
            'already_waiting' => $this->alreadyWaiting,
            'amount' => $this->gift->amount,
            'amount_formatted' => Money::ghs($this->gift->amount)->formatted(),
            'processing_fee' => $this->gift->processing_fee,
            'processing_fee_formatted' => Money::ghs((string) $this->gift->processing_fee)->formatted(),
            'total_paid' => $this->gift->total_paid,
            'total_paid_formatted' => Money::ghs((string) $this->gift->total_paid)->formatted(),
        ];
    }
}
