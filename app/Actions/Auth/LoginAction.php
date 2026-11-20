<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Email\QueueEmailAction;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\OutboundEmail;
use App\Enums\EmailCodePurpose;
use App\Enums\QueueName;
use App\Enums\RecipientType;
use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Mail\EmailCodeMail;
use App\Repositories\UserRepositoryInterface;
use App\Services\EmailCodeRequestLimiter;
use App\Services\EmailLoginCodes;
use App\Services\StaffPasswordService;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

/**
 * Step one of staff sign-in (D-054): checks the password and, only if it is right, emails a 6-digit code. A wrong
 * password, an unknown email and an account with no password all fail the same way. Someone who knows the password
 * but belongs to a deactivated account is told so and gets no code (D-055).
 *
 * @throws InvalidCredentialsException
 * @throws AccountDeactivatedException
 * @throws TooManyOtpAttemptsException
 * @throws ThrottleRequestsException
 */
final class LoginAction
{
    /**
     * Takes what the steps need: the staff lookup, the password check, the code store, the limit on emailed codes and the
     * email sender.
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly StaffPasswordService $passwords,
        private readonly EmailLoginCodes $codes,
        private readonly EmailCodeRequestLimiter $limiter,
        private readonly QueueEmailAction $emails,
    ) {}

    /**
     * Checks the password and emails the code.
     */
    public function execute(LoginDTO $dto): void
    {
        $user = $this->users->findByEmail($dto->email);

        $this->passwords->check($dto->church, $dto->email, $user, $dto->password);

        if ($user === null || ! $user->is_active) {
            throw new AccountDeactivatedException;
        }

        $this->limiter->attempt($dto->church, $dto->email, $dto->ip);

        $mail = new EmailCodeMail(
            $dto->church->name,
            $this->codes->issue($dto->church, EmailCodePurpose::SignIn, $dto->email),
            EmailCodePurpose::SignIn,
            intdiv((int) config('otp.ttl_seconds'), 60),
        );

        $this->emails->execute(new OutboundEmail(
            churchId: $dto->church->id,
            email: $dto->email,
            mailable: $mail,
            messageType: 'sign_in_code',
            recipientType: RecipientType::User,
            logContent: $mail->redactedSummary(),
            recipientId: $user->id,
            queue: QueueName::Critical,
        ));
    }
}
