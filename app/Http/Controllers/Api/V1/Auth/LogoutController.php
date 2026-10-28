<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LogoutAction;
use App\DTOs\Auth\LogoutDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ends the current session: the token for API clients, the cookie session for the browser app.
 */
class LogoutController extends Controller
{
    /**
     * Takes the logout action.
     */
    public function __construct(private readonly LogoutAction $action) {}

    /**
     * Runs LogoutAction for this request and returns the Response.
     */
    public function __invoke(Request $request): Response
    {
        $this->action->execute(LogoutDTO::fromRequest($request));

        return response()->noContent();
    }
}
