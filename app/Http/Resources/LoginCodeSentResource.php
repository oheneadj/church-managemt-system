<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Auth\OtpChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to a right password: a code was emailed and how long it lasts. It carries nothing about the person.
 *
 * @property OtpChallenge $resource
 */
class LoginCodeSentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the reply: a fixed message and how long the code stays valid.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['message' => 'Code sent', 'expires_in' => $this->resource->expiresInSeconds];
    }
}
