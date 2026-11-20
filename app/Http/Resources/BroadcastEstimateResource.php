<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Messages\BroadcastEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a broadcast would reach and cost (docs/08, Send Broadcast). Counts only: no member is named.
 *
 * @property BroadcastEstimate $resource
 */
class BroadcastEstimateResource extends JsonResource
{
    /** The answer is flat, not wrapped in `data`. */
    public static $wrap = null;

    /**
     * Shapes the answer for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $estimate = $this->resource;

        return [
            'recipients' => $estimate->recipients,
            'parts_per_message' => $estimate->partsPerMessage,
            'credits_needed' => $estimate->creditsNeeded,
            'credit_balance' => $estimate->creditBalance,
            'enough_credits' => $estimate->enoughCredits(),
        ];
    }
}
