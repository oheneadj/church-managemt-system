<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OutboundSms;
use App\Enums\RecipientType;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Jobs\SendSmsJob;
use App\ValueObjects\PhoneNumber;

/**
 * Proves that the person starting a new church owns the phone they typed. It uses the same code rules as
 * staff sign-in, but under its own scope because no church exists yet.
 */
class OnboardingOtpService
{
    /** The scope that keeps sign-up codes apart from every church's sign-in codes. */
    public const SCOPE = 'onboarding';

    /**
     * Takes the shared OTP service.
     */
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Texts a code to the phone. Unlike sign-in it always sends, because the person is not known yet.
     */
    public function request(PhoneNumber $phone): void
    {
        $code = $this->otp->issue(self::SCOPE, $phone);

        SendSmsJob::dispatch(new OutboundSms(
            churchId: null,
            phone: $phone->e164,
            message: $this->otp->message($code),
            messageType: 'onboarding_otp',
            recipientType: RecipientType::Applicant,
            logContent: '[otp redacted]',
        ))->onQueue('critical');
    }

    /**
     * Checks the code and uses it up.
     *
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function verify(PhoneNumber $phone, string $code): void
    {
        $this->otp->check(self::SCOPE, $phone, $code);
    }
}
