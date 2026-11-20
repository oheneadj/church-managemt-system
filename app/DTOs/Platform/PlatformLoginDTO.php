<?php

declare(strict_types=1);

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\PlatformLoginRequest;

/**
 * Step one of platform sign-in: an email and a password, and the address asking, for the limit on emailed codes.
 */
final readonly class PlatformLoginDTO
{
    /**
     * Holds the email, password and requesting address.
     */
    public function __construct(public string $email, public string $password, public string $ip) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(PlatformLoginRequest $request): self
    {
        return new self($request->email(), $request->password(), (string) $request->ip());
    }
}
