<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Actions\Onboarding\RequestChurchOtpAction;
use App\DTOs\Auth\OtpChallenge;
use App\DTOs\Onboarding\RequestChurchOtpDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RequestChurchOtpRequest;
use App\Http\Resources\OtpRequestedResource;

/**
 * Texts a sign-up code to the phone of the person starting a church.
 */
class RequestChurchOtpController extends Controller
{
    /**
     * Takes the request church OTP action.
     */
    public function __construct(private readonly RequestChurchOtpAction $action) {}

    /**
     * Runs RequestChurchOtpAction for this request and returns the OtpRequestedResource.
     */
    public function __invoke(RequestChurchOtpRequest $request): OtpRequestedResource
    {
        $this->action->execute(RequestChurchOtpDTO::fromRequest($request));

        return new OtpRequestedResource(new OtpChallenge((int) config('otp.ttl_seconds')));
    }
}
