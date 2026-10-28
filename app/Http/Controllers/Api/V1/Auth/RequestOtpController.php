<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestOtpAction;
use App\DTOs\Auth\RequestOtpDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Resources\OtpRequestedResource;

/**
 * Starts sign-in by texting a code to a staff phone number.
 */
class RequestOtpController extends Controller
{
    public function __construct(private readonly RequestOtpAction $action) {}

    public function __invoke(RequestOtpRequest $request): OtpRequestedResource
    {
        $this->action->execute(RequestOtpDTO::fromRequest($request));

        return new OtpRequestedResource(config('otp.ttl_seconds'));
    }
}
