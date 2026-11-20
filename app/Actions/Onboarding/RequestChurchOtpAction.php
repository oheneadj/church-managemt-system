<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\DTOs\Onboarding\RequestChurchOtpDTO;
use App\Services\OnboardingOtpService;

/**
 * Starts the sign-up of a church by texting a code to the phone of the person who is starting it.
 */
final class RequestChurchOtpAction
{
    /**
     * Takes the onboarding OTP service.
     */
    public function __construct(private readonly OnboardingOtpService $otp) {}

    /**
     * Texts the code.
     */
    public function execute(RequestChurchOtpDTO $dto): void
    {
        $this->otp->request($dto->phone);
    }
}
