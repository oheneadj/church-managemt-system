<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyOtpAction;
use App\DTOs\Auth\VerifyOtpDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\SignInResource;

/**
 * Completes sign-in. The browser app is signed in with a session cookie and gets no token in the
 * response; any other client gets a bearer token (docs/08-api.md).
 */
class VerifyOtpController extends Controller
{
    /**
     * Takes the verify OTP action.
     */
    public function __construct(private readonly VerifyOtpAction $action) {}

    /**
     * Runs VerifyOtpAction for this request and returns the SignInResource.
     */
    public function __invoke(VerifyOtpRequest $request): SignInResource
    {
        return new SignInResource($this->action->execute(VerifyOtpDTO::fromRequest($request)));
    }
}
