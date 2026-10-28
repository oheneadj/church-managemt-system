<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\SignedInStaff;
use App\DTOs\Auth\VerifyOtpDTO;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Services\AuthSessionService;
use App\Services\OtpService;

/**
 * Completes sign-in. The browser app is signed in with a session cookie and gets no token; any other
 * client gets a bearer token (docs/08-api.md).
 *
 * @throws InvalidOtpException
 * @throws TooManyOtpAttemptsException
 */
final class VerifyOtpAction
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthSessionService $sessions,
    ) {}

    public function execute(VerifyOtpDTO $dto): SignedInStaff
    {
        $user = $this->otp->verify($dto->church, $dto->phone, $dto->code);

        if ($dto->browserSession !== null) {
            $this->sessions->startBrowserSession($user, $dto->browserSession);

            return new SignedInStaff($user, $dto->church);
        }

        return new SignedInStaff($user, $dto->church, $this->sessions->startTokenSession($user)->plainTextToken);
    }
}
