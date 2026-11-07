<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\PlatformLoginAction;
use App\DTOs\Auth\OtpChallenge;
use App\DTOs\Platform\PlatformLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformLoginRequest;
use App\Http\Resources\LoginCodeSentResource;

/**
 * Step one of platform admin sign-in: the password, then a code is emailed (docs/08, Platform).
 */
class PlatformLoginController extends Controller
{
    /**
     * Takes the login action.
     */
    public function __construct(private readonly PlatformLoginAction $action) {}

    /**
     * Runs PlatformLoginAction for this request and says a code was sent.
     */
    public function __invoke(PlatformLoginRequest $request): LoginCodeSentResource
    {
        $this->action->execute(PlatformLoginDTO::fromRequest($request));

        return new LoginCodeSentResource(new OtpChallenge((int) config('otp.ttl_seconds')));
    }
}
