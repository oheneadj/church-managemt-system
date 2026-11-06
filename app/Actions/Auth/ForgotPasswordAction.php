<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Email\QueueEmailAction;
use App\DTOs\Auth\ForgotPasswordDTO;
use App\DTOs\OutboundEmail;
use App\Enums\EmailCodePurpose;
use App\Enums\QueueName;
use App\Enums\RecipientType;
use App\Mail\EmailCodeMail;
use App\Repositories\UserRepositoryInterface;
use App\Services\EmailCodeRequestLimiter;
use App\Services\EmailLoginCodes;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

/**
 * Emails a password reset code to an active staff member, and does nothing for an email that is unknown or deactivated
 * (D-054). The caller gets the same answer either way, so nobody can find out whose emails are staff. The limit on emailed
 * codes is counted for every email, known or not, so it reveals nothing either. A staff member who has no password yet sets
 * their first one the same way.
 *
 * @throws ThrottleRequestsException
 */
final class ForgotPasswordAction
{
    /**
     * Takes the staff lookup, the code store, the limit on emailed codes and the email sender.
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EmailLoginCodes $codes,
        private readonly EmailCodeRequestLimiter $limiter,
        private readonly QueueEmailAction $emails,
    ) {}

    /**
     * Emails the code when the email belongs to an active staff member.
     */
    public function execute(ForgotPasswordDTO $dto): void
    {
        $this->limiter->attempt($dto->church, $dto->email, $dto->ip);

        $user = $this->users->findByEmail($dto->email);

        if ($user === null || ! $user->is_active) {
            return;
        }

        $mail = new EmailCodeMail(
            $dto->church->name,
            $this->codes->issue($dto->church, EmailCodePurpose::PasswordReset, $dto->email),
            EmailCodePurpose::PasswordReset,
            intdiv((int) config('otp.ttl_seconds'), 60),
        );

        $this->emails->execute(new OutboundEmail(
            churchId: $dto->church->id,
            email: $dto->email,
            mailable: $mail,
            messageType: 'password_reset_code',
            recipientType: RecipientType::User,
            logContent: $mail->redactedSummary(),
            recipientId: $user->id,
            queue: QueueName::Critical,
        ));
    }
}
