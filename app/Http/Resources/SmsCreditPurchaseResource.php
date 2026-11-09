<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SmsCreditPurchase;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A purchase of SMS credits: how many credits, what they cost (exact decimal text with a formatted sibling), and how the payment
 * stands. The phone number is never sent back.
 *
 * @mixin SmsCreditPurchase
 */
class SmsCreditPurchaseResource extends JsonResource
{
    /**
     * Shapes the purchase for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sms_package_id' => $this->sms_package_id,
            'credits' => $this->credits,
            'amount' => $this->amount,
            'amount_formatted' => Money::ghs($this->amount)->formatted(),
            'network' => $this->network->value,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
