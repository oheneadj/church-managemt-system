<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ResetPasswordDTO;
use App\Enums\EmailCodePurpose;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Repositories\UserRepositoryInterface;
use App\Services\EmailLoginCodes;
use App\Services\PasswordBreachScheduler;
use Illuminate\Support\Facades\DB;

/**
 * Sets a new password with an emailed reset code (D-054). The code is checked before the person is looked up, so an unknown
 * email, a deactivated account and a wrong code all fail the same way. A good code proves the email is theirs. Setting the
 * password ends every session and token of the person (the observer and the session fingerprint), and does not sign them
 * in: they sign in with the new password. The breach check is queued, never run here.
 *
 * @throws InvalidOtpException
 * @throws TooManyOtpAttemptsException
 */
final class ResetPasswordAction
{
    /**
     * Takes the staff lookup, the code store and the breach check scheduler.
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EmailLoginCodes $codes,
        private readonly PasswordBreachScheduler $breaches,
    ) {}

    /**
     * Checks the code and sets the password.
     */
    public function execute(ResetPasswordDTO $dto): void
    {
        $this->codes->check($dto->church, EmailCodePurpose::PasswordReset, $dto->email, $dto->code);

        $user = $this->users->findByEmail($dto->email);

        if ($user === null || ! $user->is_active) {
            throw new InvalidOtpException;
        }

        DB::transaction(function () use ($user, $dto): void {
            $this->users->setPassword($user, $dto->password, proveEmail: true);

            $this->breaches->schedule($user, $dto->password);
        });
    }
}
