<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

/**
 * The answer to a request for a sign-in code: how many seconds the code stays valid.
 */
final readonly class OtpChallenge
{
    /**
     * Holds the number of seconds the code stays valid.
     */
    public function __construct(public int $expiresInSeconds) {}
}
