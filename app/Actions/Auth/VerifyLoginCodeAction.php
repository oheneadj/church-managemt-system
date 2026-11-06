<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\SignedInStaff;
use App\DTOs\Auth\VerifyLoginDTO;
use App\Enums\EmailCodePurpose;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Repositories\UserRepositoryInterface;
use App\Services\AuthSessionService;
use App\Services\EmailLoginCodes;

/**
 * Step two of staff sign-in (D-054): checks the emailed code and signs the person in. The code is checked before the
 * person is looked up, so an unknown email, a deactivated account and a wrong code all fail the same way. The first code
 * that works proves the email is theirs. The browser app is signed in with a session cookie and gets no token; any other
 * client gets a bearer token.
 *
 * @throws InvalidOtpException
 * @throws TooManyOtpAttemptsException
 */
final class VerifyLoginCodeAction
{
    /**
     * Takes the staff lookup, the code store and the session service.
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EmailLoginCodes $codes,
        private readonly AuthSessionService $sessions,
    ) {}

    /**
     * Completes sign-in.
     */
    public function execute(VerifyLoginDTO $dto): SignedInStaff
    {
        $this->codes->check($dto->church, EmailCodePurpose::SignIn, $dto->email, $dto->code);

        $user = $this->users->findByEmail($dto->email);

        if ($user === null || ! $user->is_active) {
            throw new InvalidOtpException;
        }

        $this->users->markEmailVerified($user);

        if ($dto->browserSession !== null) {
            $this->sessions->startBrowserSession($user, $dto->browserSession);

            return new SignedInStaff($user, $dto->church);
        }

        return new SignedInStaff($user, $dto->church, $this->sessions->startTokenSession($user)->plainTextToken);
    }
}
