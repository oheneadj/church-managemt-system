<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\PlatformVerifyLoginAction;
use App\DTOs\Platform\PlatformVerifyLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformVerifyLoginRequest;
use App\Http\Resources\PlatformAdminResource;

/**
 * Step two of platform admin sign-in: the emailed code signs the browser in (docs/08, Platform).
 */
class PlatformVerifyLoginController extends Controller
{
    /**
     * Takes the verify action.
     */
    public function __construct(private readonly PlatformVerifyLoginAction $action) {}

    /**
     * Runs PlatformVerifyLoginAction for this request and returns the signed-in admin.
     */
    public function __invoke(PlatformVerifyLoginRequest $request): PlatformAdminResource
    {
        return new PlatformAdminResource($this->action->execute(PlatformVerifyLoginDTO::fromRequest($request)));
    }
}
