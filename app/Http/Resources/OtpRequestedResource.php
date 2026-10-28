<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Auth\OtpChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to asking for a sign-in code. It is the same whether or not the number belongs to a staff member.
 *
 * @property OtpChallenge $resource
 */
class OtpRequestedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the reply: a fixed message and how long the code stays valid.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['message' => 'OTP sent', 'expires_in' => $this->resource->expiresInSeconds];
    }
}
