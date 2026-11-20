<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\Church;

/**
 * A request for a password reset code: the church the request came to, the email and the requester's address (for the limit
 * on emailed codes).
 */
final readonly class ForgotPasswordDTO
{
    /**
     * Holds the church, email and the requester's address.
     */
    public function __construct(public Church $church, public string $email, public string $ip) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ForgotPasswordRequest $request): self
    {
        return new self(app('current.church'), $request->email(), (string) $request->ip());
    }
}
