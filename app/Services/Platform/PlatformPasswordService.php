<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Checks a platform admin's password (D-069), as StaffPasswordService does for church staff (D-054). A wrong password, an
 * unknown email, a switched-off account and an account with no password all take the same time and give the same answer,
 * and five wrong passwords for one email lock it for 15 minutes. The count is kept under a hash of the email, apart from
 * every church's, so no address reaches Redis.
 */
class PlatformPasswordService
{
    /** A hash to check against when there is no real one, so an unknown email takes as long as a known one. */
    private static ?string $stand_in = null;

    /**
     * Checks the password for this email, or throws. The caller passes the admin it found for the email (or null), so the
     * lookup stays in the repository.
     *
     * @throws InvalidCredentialsException
     * @throws TooManyOtpAttemptsException
     */
    public function check(string $email, ?PlatformAdmin $admin, string $password): void
    {
        $attemptsKey = $this->attemptsKey($email);

        if ((int) Cache::get($attemptsKey, 0) >= config('otp.password_max_attempts')) {
            throw new TooManyOtpAttemptsException;
        }

        $matches = Hash::check($password, $admin->password ?? $this->standIn()) && $admin?->password !== null && $admin->is_active;

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
     * The cache key of an email's count of wrong passwords.
     */
    private function attemptsKey(string $email): string
    {
        return 'platform:login-attempts:'.hash('sha256', $email);
    }

    /**
     * A real hash made once per process, to check against when the account has no password.
     */
    private function standIn(): string
    {
        return self::$stand_in ??= Hash::make('stand-in for an unknown account');
    }
}
