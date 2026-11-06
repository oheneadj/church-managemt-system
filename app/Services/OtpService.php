<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\Church;
use App\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and checks one-time passcodes sent by text message: starting a church and the QR check-in. Staff sign in with
 * an emailed code instead (D-054, `EmailLoginCodes`). Only a bcrypt hash of the code is stored, and a phone is locked out
 * after too many misses.
 * The issuing and checking steps work on a "scope" (a church id, or the sign-up scope) so starting a church
 * can use the same rules before any church exists.
 */
class OtpService
{
    /**
     * Takes the one-time code store.
     */
    public function __construct(private readonly OneTimeCodes $codes) {}

    /**
     * The text message that carries a code.
     */
    public function message(string $code): string
    {
        return 'Your '.config('app.name')." code is: {$code}. Valid for ".(config('otp.ttl_seconds') / 60).' minutes. Do not share.';
    }

    /**
     * Makes a fresh code for a phone number, remembers only its hash, clears old misses and returns the code
     * so the caller can text it.
     */
    public function issue(string $scope, PhoneNumber $phone): string
    {
        return $this->codes->issue($this->codeKey($scope, $phone), $this->attemptsKey($scope, $phone));
    }

    /**
     * Checks a code for a phone number and uses it up. Too many wrong codes lock the number out.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function check(string $scope, PhoneNumber $phone, string $code): void
    {
        $this->codes->check($this->codeKey($scope, $phone), $this->attemptsKey($scope, $phone), $code);
    }

    // Keys carry the scope (the church id, or the sign-up scope) and a hash of the phone, so churches never share state and raw numbers stay out of Redis.
    /**
     * The cache key of a phone number's pending code.
     */
    private function codeKey(string $scope, PhoneNumber $phone): string
    {
        return "church:{$scope}:otp:".hash('sha256', $phone->e164);
    }

    /**
     * The cache key of a phone number's count of wrong codes.
     */
    private function attemptsKey(string $scope, PhoneNumber $phone): string
    {
        return "church:{$scope}:otp-attempts:".hash('sha256', $phone->e164);
    }
}
