<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Auth\OtpChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to asking for a password reset code. It is the same whether or not the email belongs to a staff member.
 *
 * @property OtpChallenge $resource
 */
class ResetCodeRequestedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the reply: a fixed message that promises nothing about the email, and how long a code lasts.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['message' => 'If that email belongs to a staff member, a code has been sent.', 'expires_in' => $this->resource->expiresInSeconds];
    }
}
