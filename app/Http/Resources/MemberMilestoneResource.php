<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MemberMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A spiritual milestone (docs/08, Add Member Milestone).
 *
 * @mixin MemberMilestone
 */
class MemberMilestoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'occurred_at' => $this->occurred_at->toDateString(),
            'officiant' => $this->officiant,
            'cohort' => $this->cohort,
            'notes' => $this->notes,
        ];
    }
}
