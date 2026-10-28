<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts off a signed-in staff member the moment their access changes. Tokens are deleted by
 * UserObserver; browser sessions cannot be deleted from a user id, so this checks on every request
 * that the user is still active and still has the phone number the session was opened with.
 */
class EnsureStaffIsCurrent
{
    public function __construct(private readonly AuthSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $session = $request->hasSession() ? $request->session() : null;

        $revoked = $user === null
            || ! $user->is_active
            || ($session !== null && $this->sessions->phoneChangedSinceSignIn($user, $session));

        if ($revoked) {
            if ($session !== null) {
                $this->sessions->endBrowserSession($session);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
