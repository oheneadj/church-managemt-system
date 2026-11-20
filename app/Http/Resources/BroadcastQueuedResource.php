<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Messages\QueuedBroadcast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The answer to "send a broadcast" (docs/08, Send Broadcast): it is queued, who it is estimated to reach and what that costs.
 *
 * @property QueuedBroadcast $resource
 */
class BroadcastQueuedResource extends JsonResource
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
        return [
            'queued' => true,
            'broadcast_id' => $this->resource->broadcast->id,
            'estimated_recipients' => $this->resource->estimate->recipients,
            'estimated_credit_cost' => $this->resource->estimate->creditsNeeded,
            'current_credit_balance' => $this->resource->estimate->creditBalance,
        ];
    }
}
