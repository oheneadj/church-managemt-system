<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Activity\ActivityItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the recent activity feed: its kind, the plain sentence, who did it and when. Nothing else from the change
 * history is shown.
 *
 * @mixin ActivityItem
 */
class ActivityResource extends JsonResource
{
    /**
     * Shapes the activity line for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'text' => $this->text,
            'actor_name' => $this->actorName,
            'occurred_at' => $this->occurredAt?->toIso8601String(),
        ];
    }
}
