<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EmailCodePurpose;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\Church;

/**
 * The codes emailed to staff (D-054): sign-in, password reset and invitation. They share the one-time code rules with
 * the codes sent by text, but each church, each purpose and each email has its own code and its own count of misses,
 * so a sign-in code can never be used to reset a password, and one church's state is never another's.
 */
class EmailLoginCodes
{
    /**
     * Takes the one-time code store.
     */
    public function __construct(private readonly OneTimeCodes $codes) {}

    /**
     * Makes a fresh code for this email and purpose and returns it, so the caller can email it.
     */
    public function issue(Church $church, EmailCodePurpose $purpose, string $email): string
    {
        return $this->codes->issue($this->codeKey($church, $purpose, $email), $this->attemptsKey($church, $purpose, $email));
    }

    /**
     * Checks a code for this email and purpose and uses it up.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function check(Church $church, EmailCodePurpose $purpose, string $email, string $code): void
    {
        $this->codes->check($this->codeKey($church, $purpose, $email), $this->attemptsKey($church, $purpose, $email), $code);
    }

    /**
     * Drops the pending code and the count of wrong codes for this email and purpose, for the support runbook (docs/16).
     */
    public function forget(Church $church, EmailCodePurpose $purpose, string $email): void
    {
        $this->codes->forget($this->codeKey($church, $purpose, $email), $this->attemptsKey($church, $purpose, $email));
    }

    /**
     * The cache key of a pending code. The email is hashed, so no address is kept in Redis.
     */
    private function codeKey(Church $church, EmailCodePurpose $purpose, string $email): string
    {
        return "church:{$church->id}:email-code:{$purpose->value}:".hash('sha256', $email);
    }

    /**
     * The cache key of the count of wrong codes.
     */
    private function attemptsKey(Church $church, EmailCodePurpose $purpose, string $email): string
    {
        return "church:{$church->id}:email-code-attempts:{$purpose->value}:".hash('sha256', $email);
    }
}
