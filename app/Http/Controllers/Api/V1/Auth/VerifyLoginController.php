<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyLoginCodeAction;
use App\DTOs\Auth\VerifyLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyLoginRequest;
use App\Http\Resources\SignInResource;

/**
 * Step two of staff sign-in. The browser app is signed in with a session cookie and gets no token in the response; any
 * other client gets a bearer token (docs/08-api.md).
 */
class VerifyLoginController extends Controller
{
    /**
     * Takes the verify action.
     */
    public function __construct(private readonly VerifyLoginCodeAction $action) {}

    /**
     * Runs VerifyLoginCodeAction for this request and returns the SignInResource.
     */
    public function __invoke(VerifyLoginRequest $request): SignInResource
    {
        return new SignInResource($this->action->execute(VerifyLoginDTO::fromRequest($request)));
    }
}
