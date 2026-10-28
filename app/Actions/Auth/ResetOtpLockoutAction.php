<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ResetOtpLockoutDTO;
use App\Services\OtpService;

/**
 * Clears a phone number's sign-in lockout and any pending code. Used by the support runbook (docs/16).
 */
final class ResetOtpLockoutAction
{
    public function __construct(private readonly OtpService $otp) {}

    public function execute(ResetOtpLockoutDTO $dto): void
    {
        $this->otp->resetLockout($dto->church, $dto->phone);
    }
}
