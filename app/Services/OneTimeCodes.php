<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * The store behind every one-time code: staff sign-in by text or by email, starting a church, the QR check-in (agreement
 * section 16). Only a hash of the code is kept, it works once, and too many wrong tries lock it. The caller decides what
 * the two cache keys are, so each use keeps its own state and raw phone numbers or emails never reach Redis.
 */
class OneTimeCodes
{
    /**
     * Makes a fresh code, remembers only its hash, clears old misses and returns the code so the caller can send it.
     */
    public function issue(string $codeKey, string $attemptsKey): string
    {
        $code = $this->generate();

        Cache::put($codeKey, Hash::make($code), config('otp.ttl_seconds'));
        Cache::forget($attemptsKey);

        return $code;
    }

    /**
     * Checks a code and uses it up. Too many wrong codes lock it out; the miss that reaches the limit already reports
     * the lockout (QA-01 TC-01-05), not one more "invalid" answer.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function check(string $codeKey, string $attemptsKey, string $code): void
    {
        if ((int) Cache::get($attemptsKey, 0) >= config('otp.max_attempts')) {
            throw new TooManyOtpAttemptsException;
        }

        $hash = Cache::get($codeKey);

        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            Cache::add($attemptsKey, 0, config('otp.lockout_seconds'));

            if (Cache::increment($attemptsKey) >= config('otp.max_attempts')) {
                throw new TooManyOtpAttemptsException;
            }

            throw new InvalidOtpException;
        }

        $this->forget($codeKey, $attemptsKey);
    }

    /**
     * Throws away a pending code and its count of misses.
     */
    public function forget(string $codeKey, string $attemptsKey): void
    {
        Cache::forget($codeKey);
        Cache::forget($attemptsKey);
    }

    /**
     * A random numeric code of the configured length.
     */
    private function generate(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
