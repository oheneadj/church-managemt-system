<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where the church's digital gifts are paid out (docs/08, Payout Account). The account number is never sent in full: only its
 * last three digits, enough for the admin to recognise it.
 *
 * @mixin Church
 */
class PayoutAccountResource extends JsonResource
{
    /**
     * Shapes the payout account for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $number = $this->payout_account_number;

        return [
            'status' => $this->payoutStatus(),
            'method' => $this->payout_method?->value,
            'network' => $this->payout_network?->value,
            'bank_code' => $this->payout_bank_code,
            'account_number_hint' => $number === null ? null : '***'.substr($number, -3),
            'account_name' => $this->payout_account_name,
            'verified_at' => $this->payout_verified_at?->toIso8601String(),
            'error' => $this->payout_check_error,
        ];
    }
}
