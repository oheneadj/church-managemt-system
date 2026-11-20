<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Starts and ends platform admin sessions (D-069). The platform side is used from a browser only, so there are no bearer
 * tokens: a sign-in is an httpOnly session cookie with a fresh session id, and a sign-out discards the session.
 */
class PlatformSessionService
{
    /**
     * Signs the browser in with a session cookie and a fresh session id.
     */
    public function start(PlatformAdmin $admin, Session $session): void
    {
        $admin->forceFill(['last_login_at' => now()])->save();
        Auth::guard('platform')->login($admin);
        // A fresh session id on sign-in stops anyone who planted an id earlier from riding the new session.
        $session->regenerate();
    }

    /**
     * Signs the browser out and discards its session.
     */
    public function end(Session $session): void
    {
        Auth::guard('platform')->logout();
        $session->invalidate();
    }
}
