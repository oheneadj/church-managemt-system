<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MessageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the message log as the church sees it. The provider's reference and raw answer are the platform's business and
 * are not part of it.
 *
 * @mixin MessageLog
 */
class MessageLogResource extends JsonResource
{
    /**
     * Shapes the message for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'recipient_type' => $this->recipient_type->value,
            'recipient_id' => $this->recipient_id,
            'recipient_phone' => $this->recipient_phone,
            'recipient_email' => $this->recipient_email,
            'message_type' => $this->message_type,
            'content' => $this->content,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'cost_units' => $this->cost_units,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
