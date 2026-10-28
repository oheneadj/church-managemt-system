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

        $code = $this->generateCode();

        Cache::put($this->codeKey($church, $phone), Hash::make($code), config('otp.ttl_seconds'));
        Cache::forget($this->attemptsKey($church, $phone));

        SendSmsJob::dispatch(new OutboundSms(
            churchId: $church->id,
            phone: $phone->e164,
            message: 'Your '.config('app.name')." code is: {$code}. Valid for ".(config('otp.ttl_seconds') / 60).' minutes. Do not share.',
            messageType: 'otp',
            recipientType: RecipientType::User,
            recipientId: $user->id,
            logContent: '[otp redacted]',
        ))->onQueue('critical');
    }

    /**
     * Checks a code and returns the staff member it belongs to.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function verify(Church $church, PhoneNumber $phone, string $code): User
    {
        $attemptsKey = $this->attemptsKey($church, $phone);

        if ((int) Cache::get($attemptsKey, 0) >= config('otp.max_attempts')) {
            throw new TooManyOtpAttemptsException;
        }

        $hash = Cache::get($this->codeKey($church, $phone));

        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            Cache::add($attemptsKey, 0, config('otp.lockout_seconds'));

            // The fifth miss already reports the lockout (QA-01 TC-01-05), not a sixth invalid-code answer.
            if (Cache::increment($attemptsKey) >= config('otp.max_attempts')) {
                throw new TooManyOtpAttemptsException;
            }

            throw new InvalidOtpException;
        }

        $user = $this->users->findActiveByPhone($phone->e164);

        if ($user === null) {
            throw new InvalidOtpException;
        }

        Cache::forget($this->codeKey($church, $phone));
        Cache::forget($attemptsKey);

        return $user;
    }

    /**
     * Clears a phone's lockout and any pending code. Used by the support runbook (docs/16).
     */
    public function resetLockout(Church $church, PhoneNumber $phone): void
    {
        Cache::forget($this->attemptsKey($church, $phone));
        Cache::forget($this->codeKey($church, $phone));
    }

    /**
     * A random numeric code of the configured length.
     */
    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    // Keys carry the church id and a hash of the phone, so churches never share state and raw numbers stay out of Redis.
    /**
     * The cache key of a phone number's pending code.
     */
    private function codeKey(Church $church, PhoneNumber $phone): string
    {
        return "church:{$church->id}:otp:".hash('sha256', $phone->e164);
    }

    /**
     * The cache key of a phone number's count of wrong codes.
     */
    private function attemptsKey(Church $church, PhoneNumber $phone): string
    {
        return "church:{$church->id}:otp-attempts:".hash('sha256', $phone->e164);
    }
}
