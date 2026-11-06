<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\OtpChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\LoginCodeSentResource;

/**
 * Step one of staff sign-in: checks the email and password and emails a code.
 */
class LoginController extends Controller
{
    /**
     * Takes the login action.
     */
    public function __construct(private readonly LoginAction $action) {}

    /**
     * Runs LoginAction for this request and returns the LoginCodeSentResource.
     */
    public function __invoke(LoginRequest $request): LoginCodeSentResource
    {
        $this->action->execute(LoginDTO::fromRequest($request));

        return new LoginCodeSentResource(new OtpChallenge((int) config('otp.ttl_seconds')));
    }
}
