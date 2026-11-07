<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\PlatformLogoutAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ends the platform admin's session (docs/08, Platform).
 */
class PlatformLogoutController extends Controller
{
    /**
     * Takes the logout action.
     */
    public function __construct(private readonly PlatformLogoutAction $action) {}

    /**
     * Runs PlatformLogoutAction for this request and returns an empty answer.
     */
    public function __invoke(Request $request): Response
    {
        $this->action->execute($request->session());

        return response()->noContent();
    }
}
