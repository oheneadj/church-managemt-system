<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Email\QueueEmailAction;
use App\DTOs\OutboundEmail;
use App\DTOs\Staff\InviteStaffDTO;
use App\Enums\EmailCodePurpose;
use App\Enums\QueueName;
use App\Enums\RecipientType;
use App\Events\StaffInvited;
use App\Mail\EmailCodeMail;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Services\EmailLoginCodes;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Invites a staff member (D-055 point 7). The account is made with a role and no password, the change is written to the
 * history, and an invitation code is emailed. The code is a password reset code in the code store, so the person chooses
 * their first password with `auth/password/reset` and shares its attempt limit and lockout; only the wording of the email
 * says "invitation". If it expires, "Forgot password" sends a new one.
 */
final class InviteStaffAction
{
    /**
     * Takes the staff repository, the code store and the email sender.
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EmailLoginCodes $codes,
        private readonly QueueEmailAction $emails,
    ) {}

    /**
     * Creates the staff member and sends the invitation.
     */
    public function execute(InviteStaffDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $staff = $this->users->create([
                'church_id' => $dto->actor->church_id,
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'is_active' => true,
            ]);
            $this->users->changeRole($staff, $dto->role);

            StaffInvited::dispatch($staff, $dto->actor, $dto->role->value);
            $this->sendInvitation($dto, $staff);

            return $staff;
        });
    }

    /**
     * Queues the email that carries the invitation code.
     */
    private function sendInvitation(InviteStaffDTO $dto, User $staff): void
    {
        $church = $dto->actor->church ?? throw new LogicException('A staff member always belongs to a church.');
        $mail = new EmailCodeMail(
            $church->name,
            $this->codes->issue($church, EmailCodePurpose::PasswordReset, $dto->email),
            EmailCodePurpose::StaffInvitation,
            intdiv((int) config('otp.ttl_seconds'), 60),
        );

        $this->emails->execute(new OutboundEmail(
            churchId: $church->id,
            email: $dto->email,
            mailable: $mail,
            messageType: 'staff_invitation_code',
            recipientType: RecipientType::User,
            logContent: $mail->redactedSummary(),
            recipientId: $staff->id,
            queue: QueueName::Critical,
        ));
    }
}
