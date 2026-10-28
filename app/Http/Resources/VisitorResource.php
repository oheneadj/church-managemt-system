<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VisitorRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A visitor record (docs/07, visitor_records).
 *
 * @mixin VisitorRecord
 */
class VisitorResource extends JsonResource
{
    /**
     * Shapes the visitor for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'how_heard' => $this->how_heard,
            'first_visit_date' => $this->first_visit_date->toDateString(),
            'last_visit_date' => $this->last_visit_date?->toDateString(),
            'visit_count' => $this->visit_count,
            'follow_up_status' => $this->follow_up_status->value,
            'follow_up_status_label' => $this->follow_up_status->label(),
        ];
    }
}
