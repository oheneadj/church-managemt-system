<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ends the current session by revoking the token that made the request.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->user()?->currentAccessToken()->delete();

        return response()->noContent();
    }
}
