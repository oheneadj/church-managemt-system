<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OutboundSms;
use App\Enums\RecipientType;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and checks one-time passcodes for staff sign-in.
 * Only a bcrypt hash of the code is stored, per church, and a phone is locked out after too many misses.
 * The issuing and checking steps work on a "scope" (a church id, or the sign-up scope) so starting a church
 * can use the same rules before any church exists.
 */
class OtpService
{
    /**
     * Takes the user repository.
     */
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Sends a code if the phone belongs to an active staff member of this church.
     * It returns normally either way, so the caller cannot learn which numbers exist.
     */
    public function request(Church $church, PhoneNumber $phone): void
    {
        $user = $this->users->findActiveByPhone($phone->e164);

        if ($user === null) {
            // I still pay for one hash so an unknown number takes as long as a known one.
            Hash::make('unused');

            return;
        }

        $code = $this->issue($church->id, $phone);

        SendSmsJob::dispatch(new OutboundSms(
            churchId: $church->id,
            phone: $phone->e164,
            message: $this->message($code),
            messageType: 'otp',
            recipientType: RecipientType::User,
            recipientId: $user->id,
            logContent: '[otp redacted]',
        ))->onQueue('critical');
    }

    /**
     * The text message that carries a code.
     */
    public function message(string $code): string
    {
        return 'Your '.config('app.name')." code is: {$code}. Valid for ".(config('otp.ttl_seconds') / 60).' minutes. Do not share.';
    }

    /**
     * Checks a code and returns the staff member it belongs to.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function verify(Church $church, PhoneNumber $phone, string $code): User
    {
        $this->check($church->id, $phone, $code);

        $user = $this->users->findActiveByPhone($phone->e164);

        if ($user === null) {
            throw new InvalidOtpException;
        }

        return $user;
    }

    /**
     * Makes a fresh code for a phone number, remembers only its hash, clears old misses and returns the code
     * so the caller can text it.
     */
    public function issue(string $scope, PhoneNumber $phone): string
    {
        $code = $this->generateCode();

        Cache::put($this->codeKey($scope, $phone), Hash::make($code), config('otp.ttl_seconds'));
        Cache::forget($this->attemptsKey($scope, $phone));

        return $code;
    }

    /**
     * Checks a code for a phone number and uses it up. Too many wrong codes lock the number out.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function check(string $scope, PhoneNumber $phone, string $code): void
    {
        $attemptsKey = $this->attemptsKey($scope, $phone);

        if ((int) Cache::get($attemptsKey, 0) >= config('otp.max_attempts')) {
            throw new TooManyOtpAttemptsException;
        }

        $hash = Cache::get($this->codeKey($scope, $phone));

        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            Cache::add($attemptsKey, 0, config('otp.lockout_seconds'));

            // The fifth miss already reports the lockout (QA-01 TC-01-05), not a sixth invalid-code answer.
            if (Cache::increment($attemptsKey) >= config('otp.max_attempts')) {
                throw new TooManyOtpAttemptsException;
            }

            throw new InvalidOtpException;
        }

        Cache::forget($this->codeKey($scope, $phone));
        Cache::forget($attemptsKey);
    }

    /**
     * Clears a phone's lockout and any pending code. Used by the support runbook (docs/16).
     */
    public function resetLockout(Church $church, PhoneNumber $phone): void
    {
        Cache::forget($this->attemptsKey($church->id, $phone));
        Cache::forget($this->codeKey($church->id, $phone));
    }

    /**
     * A random numeric code of the configured length.
     */
    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
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
