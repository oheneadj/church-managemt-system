<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Actions\Email\QueueEmailAction;
use App\DTOs\OutboundEmail;
use App\DTOs\Platform\PlatformLoginDTO;
use App\Enums\EmailCodePurpose;
use App\Enums\QueueName;
use App\Enums\RecipientType;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Mail\EmailCodeMail;
use App\Repositories\PlatformAdminRepositoryInterface;
use App\Services\Platform\PlatformCodeRequestLimiter;
use App\Services\Platform\PlatformLoginCodes;
use App\Services\Platform\PlatformPasswordService;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

/**
 * Step one of platform admin sign-in (D-069): checks the password and, only if it is right and the account is switched on,
 * emails a 6-digit code. A wrong password, an unknown email and a switched-off account all fail the same way.
 *
 * @throws InvalidCredentialsException
 * @throws TooManyOtpAttemptsException
 * @throws ThrottleRequestsException
 */
final class PlatformLoginAction
{
    /**
     * Takes what the step needs: the account lookup, the password check, the code store, the limit on emailed codes and the
     * email sender.
     */
    public function __construct(
        private readonly PlatformAdminRepositoryInterface $admins,
        private readonly PlatformPasswordService $passwords,
        private readonly PlatformLoginCodes $codes,
        private readonly PlatformCodeRequestLimiter $limiter,
        private readonly QueueEmailAction $emails,
    ) {}

    /**
     * Checks the password and emails the code.
     */
    public function execute(PlatformLoginDTO $dto): void
    {
        $admin = $this->admins->findByEmail($dto->email);
        $this->passwords->check($dto->email, $admin, $dto->password);

        $this->limiter->attempt($dto->email, $dto->ip);

        $mail = new EmailCodeMail(
            (string) config('app.name'),
            $this->codes->issue($dto->email),
            EmailCodePurpose::SignIn,
            intdiv((int) config('otp.ttl_seconds'), 60),
        );

        $this->emails->execute(new OutboundEmail(
            churchId: null,
            email: $dto->email,
            mailable: $mail,
            messageType: 'sign_in_code',
            recipientType: RecipientType::User,
            logContent: $mail->redactedSummary(),
            recipientId: $admin?->id,
            queue: QueueName::Critical,
        ));
    }
}
