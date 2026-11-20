<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ResetStaffLockoutDTO;
use App\Enums\EmailCodePurpose;
use App\Services\EmailLoginCodes;
use App\Services\StaffPasswordService;

/**
 * Lifts a staff email's sign-in lockout and drops any code waiting for it, so the person can start again from the
 * password. Used by the support runbook (docs/16). It covers the two lockouts of D-054: wrong passwords, and wrong
 * emailed codes for signing in or for choosing a new password.
 */
final class ResetStaffLockoutAction
{
    /**
     * The codes that can be locked: the sign-in code and the password reset code.
     *
     * @var list<EmailCodePurpose>
     */
    private const array PURPOSES = [EmailCodePurpose::SignIn, EmailCodePurpose::PasswordReset];

    /**
     * Takes the password check and the emailed code store.
     */
    public function __construct(
        private readonly StaffPasswordService $passwords,
        private readonly EmailLoginCodes $codes,
    ) {}

    /**
     * Clears the lockouts and pending codes for the email.
     */
    public function execute(ResetStaffLockoutDTO $dto): void
    {
        $this->passwords->resetLockout($dto->church, $dto->email);

        foreach (self::PURPOSES as $purpose) {
            $this->codes->forget($dto->church, $purpose, $dto->email);
        }
    }
}
