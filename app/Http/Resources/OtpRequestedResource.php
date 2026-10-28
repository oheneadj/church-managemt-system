<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to asking for a sign-in code. It is the same whether or not the number belongs to a staff member.
 * The wrapped value is how many seconds the code stays valid.
 */
class OtpRequestedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['message' => 'OTP sent', 'expires_in' => $this->resource];
    }
}
