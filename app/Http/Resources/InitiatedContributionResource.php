<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Contribution;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gift whose payment has been started (docs/08, Initiate MoMo / Card Payment). `reference` is the gift's own id, the one the
 * provider is given; `authorization_url` is only for card payments, which are not taken yet.
 *
 * @mixin Contribution
 */
class InitiatedContributionResource extends JsonResource
{
    /**
     * Shapes the started payment for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->id,
            'authorization_url' => null,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'amount_formatted' => Money::ghs($this->amount)->formatted(),
            'processing_fee' => $this->processing_fee,
            'processing_fee_formatted' => Money::ghs((string) $this->processing_fee)->formatted(),
            'total_paid' => $this->total_paid,
            'total_paid_formatted' => Money::ghs((string) $this->total_paid)->formatted(),
        ];
    }
}
