<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Contribution;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gift (docs/08, Contributions). The amount is an exact decimal string with a formatted sibling, and the
 * formatting happens only in Money. A gift with no member is anonymous.
 *
 * @mixin Contribution
 */
class ContributionResource extends JsonResource
{
    /**
     * Shapes the gift for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amount = Money::ghs($this->amount);

        return [
            'id' => $this->id,
            'member' => $this->member === null ? null : ['id' => $this->member->id, 'full_name' => $this->member->full_name],
            'is_anonymous' => $this->member_id === null,
            'service' => $this->service === null ? null : ['id' => $this->service->id, 'name' => $this->service->name, 'service_date' => $this->service->service_date->toDateString()],
            'fund_type' => ['id' => $this->fund_type_id, 'name' => $this->fund_name],
            'pledge' => $this->whenLoaded('pledge', fn () => $this->pledge === null ? null : ['id' => $this->pledge->id, 'description' => $this->pledge->description]),
            'amount' => $amount->amount,
            'amount_formatted' => $amount->formatted(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'is_voided' => $this->is_voided,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'voided_by' => $this->voidedBy?->name,
            'recorded_by' => $this->recordedBy->name,
            'notes' => $this->notes,
            'contributed_at' => $this->contributed_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
