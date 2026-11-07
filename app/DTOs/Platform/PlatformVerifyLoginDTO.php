<?php

declare(strict_types=1);

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\PlatformVerifyLoginRequest;
use Illuminate\Contracts\Session\Session;

/**
 * Step two of platform sign-in: an emailed code to check, and the browser session to sign in.
 */
final readonly class PlatformVerifyLoginDTO
{
    /**
     * Holds the email, code and session.
     */
    public function __construct(public string $email, public string $code, public Session $session) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(PlatformVerifyLoginRequest $request): self
    {
        return new self($request->email(), $request->code(), $request->session());
    }
}
