<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Services\AuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ends the current session: the token for API clients, the cookie session for the browser app.
 */
class LogoutController extends Controller
{
    public function __construct(private readonly AuthSessionService $sessions) {}

    public function __invoke(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($request->hasSession()) {
            $this->sessions->endBrowserSession($request->session());
        }

        return response()->noContent();
    }
}
