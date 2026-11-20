<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an emailed 6-digit code is for (D-054). The three share one email template; only the wording changes.
 */
enum EmailCodePurpose: string
{
    case SignIn = 'sign_in';
    case PasswordReset = 'password_reset';
    case StaffInvitation = 'staff_invitation';

    /**
     * What to call the code in a sentence, for example "sign-in code".
     */
    public function label(): string
    {
        return match ($this) {
            self::SignIn => 'sign-in code',
            self::PasswordReset => 'password reset code',
            self::StaffInvitation => 'invitation code',
        };
    }
}
