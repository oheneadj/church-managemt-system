<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Starts a staff session by issuing an expiring Sanctum token.
 */
class AuthTokenService
{
    public function startSession(User $user): NewAccessToken
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->createToken('staff', ['*'], now()->addHours((int) config('otp.staff_token_hours')));
    }
}
