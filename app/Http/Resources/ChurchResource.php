<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The church the signed-in user belongs to. Payout and billing details are deliberately left out.
 *
 * @mixin Church
 */
class ChurchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan->value,
            'plan_label' => $this->plan->label(),
            'sms_credits' => $this->sms_credits,
        ];
    }
}
