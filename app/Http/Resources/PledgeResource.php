<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pledge;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pledge (docs/08, Pledges). Each amount is an exact decimal string with a formatted sibling, and the formatting
 * happens only in Money. The gifts that pay it are included only when they were loaded (the single-pledge view).
 *
 * @mixin Pledge
 */
class PledgeResource extends JsonResource
{
    /**
     * Shapes the pledge for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pledged = Money::ghs($this->amount_pledged);
        $paid = Money::ghs($this->amount_paid);
        $outstanding = Money::ghs($this->amount_outstanding);

        return [
            'id' => $this->id,
            'member' => ['id' => $this->member->id, 'full_name' => $this->member->full_name],
            'fund_type' => ['id' => $this->fundType->id, 'name' => $this->fundType->name],
            'description' => $this->description,
            'amount_pledged' => $pledged->amount,
            'amount_pledged_formatted' => $pledged->formatted(),
            'amount_paid' => $paid->amount,
            'amount_paid_formatted' => $paid->formatted(),
            'amount_outstanding' => $outstanding->amount,
            'amount_outstanding_formatted' => $outstanding->formatted(),
            'progress_percent' => $this->progress_percent,
            'target_date' => $this->target_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601String(),
            'contributions' => ContributionResource::collection($this->whenLoaded('contributions')),
        ];
    }
}
