<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

/**
 * Starts and ends staff sessions. The browser app gets an httpOnly session cookie (so a page refresh
 * keeps the user signed in); any other client gets an expiring bearer token.
 */
class AuthSessionService
{
    /** Session key holding a fingerprint of the phone number the session was opened with. */
    public const PHONE_FINGERPRINT_KEY = 'auth.phone_fingerprint';

    /**
     * Opens an expiring bearer-token session for a non-browser client.
     */
    public function startTokenSession(User $user): NewAccessToken
    {
        $this->recordLogin($user);

        return $user->createToken('staff', ['*'], now()->addHours((int) config('otp.staff_token_hours')));
    }

    /**
     * Signs the browser app in with a session cookie and a fresh session id.
     */
    public function startBrowserSession(User $user, Session $session): void
    {
        $this->recordLogin($user);

        Auth::guard('web')->login($user);
        // A fresh session id on sign-in stops anyone who planted an id earlier from riding the new session.
        $session->regenerate();
        $session->put(self::PHONE_FINGERPRINT_KEY, self::fingerprint($user));
    }

    /**
     * Signs the browser app out and discards its session.
     */
    public function endBrowserSession(Session $session): void
    {
        Auth::guard('web')->logout();
        $session->invalidate();
    }

    /**
     * True when the session was opened with a different phone number than the user has now.
     */
    public function phoneChangedSinceSignIn(User $user, Session $session): bool
    {
        $opened = $session->get(self::PHONE_FINGERPRINT_KEY);

        return is_string($opened) && ! hash_equals($opened, self::fingerprint($user));
    }

    /**
     * Stamps the time of the staff member's last sign-in.
     */
    private function recordLogin(User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
    }

    /**
     * A hash of the phone number, used to notice when it changes under an open session.
     */
    private static function fingerprint(User $user): string
    {
        return hash('sha256', $user->phone);
    }
}
