<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Church;

/**
 * Step one of staff sign-in: the church the request came to, the email, the password and the address it came from (for
 * the limit on emailed codes).
 */
final readonly class LoginDTO
{
    /**
     * Holds the church, email, password and the requester's address.
     */
    public function __construct(public Church $church, public string $email, public string $password, public string $ip) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(LoginRequest $request): self
    {
        return new self(app('current.church'), $request->email(), $request->password(), (string) $request->ip());
    }
}
