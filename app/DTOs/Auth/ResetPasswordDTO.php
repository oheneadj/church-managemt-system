<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\Church;

/**
 * Choosing a new password: the church the request came to, the email, the emailed code and the new password.
 */
final readonly class ResetPasswordDTO
{
    /**
     * Holds the church, email, code and new password.
     */
    public function __construct(public Church $church, public string $email, public string $code, public string $password) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ResetPasswordRequest $request): self
    {
        return new self(app('current.church'), $request->email(), $request->code(), $request->password());
    }
}
