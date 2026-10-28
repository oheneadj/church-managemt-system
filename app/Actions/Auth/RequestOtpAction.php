<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\RequestOtpDTO;
use App\Services\OtpService;

/**
 * Starts sign-in by texting a code to a staff phone number. It returns normally whether or not the number
 * belongs to a staff member, so a caller cannot learn which numbers exist.
 */
final class RequestOtpAction
{
    /**
     * Takes the OTP service.
     */
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Starts sign-in by texting a code to a staff phone number.
     */
    public function execute(RequestOtpDTO $dto): void
    {
        $this->otp->request($dto->church, $dto->phone);
    }
}
