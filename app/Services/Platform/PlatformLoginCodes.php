<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Services\OneTimeCodes;

/**
 * The sign-in codes emailed to platform admins (D-069). They follow the same one-time code rules as church staff's
 * (EmailLoginCodes) but live under the platform's own keys, so a church's code can never open the platform side and the
 * count of wrong codes is the platform's own.
 */
class PlatformLoginCodes
{
    /**
     * Takes the one-time code store.
     */
    public function __construct(private readonly OneTimeCodes $codes) {}

    /**
     * Makes a fresh code for this email and returns it, so the caller can email it.
     */
    public function issue(string $email): string
    {
        return $this->codes->issue($this->codeKey($email), $this->attemptsKey($email));
    }

    /**
     * Checks a code for this email and uses it up.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function check(string $email, string $code): void
    {
        $this->codes->check($this->codeKey($email), $this->attemptsKey($email), $code);
    }

    /**
     * The cache key of a pending code. The email is hashed, so no address is kept in Redis.
     */
    private function codeKey(string $email): string
    {
        return 'platform:email-code:sign_in:'.hash('sha256', $email);
    }

    /**
     * The cache key of the count of wrong codes.
     */
    private function attemptsKey(string $email): string
    {
        return 'platform:email-code-attempts:sign_in:'.hash('sha256', $email);
    }
}
