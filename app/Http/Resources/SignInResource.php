<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Auth\SignedInStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to a successful sign-in. A bearer token is included only when there is one (not for the browser app).
 *
 * @mixin SignedInStaff
 */
class SignInResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...($this->token === null ? [] : ['token' => $this->token, 'token_type' => 'Bearer']),
            'user' => new UserResource($this->user),
            'church' => new ChurchResource($this->church),
        ];
    }
}
