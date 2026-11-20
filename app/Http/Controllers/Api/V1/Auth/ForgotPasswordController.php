<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ForgotPasswordAction;
use App\DTOs\Auth\ForgotPasswordDTO;
use App\DTOs\Auth\OtpChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Resources\ResetCodeRequestedResource;

/**
 * Asks for a password reset code. It answers the same for every email.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Takes the forgot password action.
     */
    public function __construct(private readonly ForgotPasswordAction $action) {}

    /**
     * Runs ForgotPasswordAction for this request and returns the ResetCodeRequestedResource.
     */
    public function __invoke(ForgotPasswordRequest $request): ResetCodeRequestedResource
    {
        $this->action->execute(ForgotPasswordDTO::fromRequest($request));

        return new ResetCodeRequestedResource(new OtpChallenge((int) config('otp.ttl_seconds')));
    }
}
