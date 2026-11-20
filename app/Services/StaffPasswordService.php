<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Checks a staff password (D-054). A wrong password, an unknown email and an account with no password all take the same
 * time and give the same answer, and five wrong passwords for one email in one church lock it for 15 minutes. The count
 * is kept per church, under a hash of the email, so churches never share state and no address reaches Redis.
 */
class StaffPasswordService
{
    /** A hash to check against when there is no real one, so an unknown email takes as long as a known one. */
    private static ?string $stand_in = null;

    /**
     * Checks the password for this email in this church, or throws. The caller passes the staff member it found for the
     * email (or null), so the lookup stays in the repository.
     *
     * @throws InvalidCredentialsException
     * @throws TooManyOtpAttemptsException
     */
    public function check(Church $church, string $email, ?User $user, string $password): void
    {
        $attemptsKey = $this->attemptsKey($church, $email);

        if ((int) Cache::get($attemptsKey, 0) >= config('otp.password_max_attempts')) {
            throw new TooManyOtpAttemptsException;
        }

        $matches = Hash::check($password, $user->password ?? $this->standIn()) && $user?->password !== null;

        if (! $matches) {
            Cache::add($attemptsKey, 0, config('otp.password_lockout_seconds'));

            if (Cache::increment($attemptsKey) >= config('otp.password_max_attempts')) {
                throw new TooManyOtpAttemptsException;
            }

            throw new InvalidCredentialsException;
        }

        Cache::forget($attemptsKey);
    }

    /**
     * Lifts the lockout of an email in a church, for the support runbook (docs/16).
     */
    public function resetLockout(Church $church, string $email): void
    {
        Cache::forget($this->attemptsKey($church, $email));
    }

    /**
     * The cache key of an email's count of wrong passwords in a church.
     */
    private function attemptsKey(Church $church, string $email): string
    {
        return "church:{$church->id}:login-attempts:".hash('sha256', $email);
    }

    /**
     * A real hash made once per process, to check against when the account has no password.
     */
    private function standIn(): string
    {
        return self::$stand_in ??= Hash::make('stand-in for an unknown account');
    }
}
