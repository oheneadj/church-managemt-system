<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\RequestQrCheckinOtpAction;
use App\DTOs\Attendance\RequestQrCheckinOtpDTO;
use App\DTOs\Auth\OtpChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RequestQrCheckinOtpRequest;
use App\Http\Resources\OtpRequestedResource;

/**
 * Texts a check-in code to a member who scanned the QR code (docs/08, Request QR Check-in Code). It answers the same for any phone number.
 */
class RequestQrCheckinOtpController extends Controller
{
    /**
     * Takes the request QR check-in code action.
     */
    public function __construct(private readonly RequestQrCheckinOtpAction $action) {}

    /**
     * Runs RequestQrCheckinOtpAction for this request and returns the OtpRequestedResource.
     */
    public function __invoke(RequestQrCheckinOtpRequest $request): OtpRequestedResource
    {
        $this->action->execute(RequestQrCheckinOtpDTO::fromRequest($request));

        return new OtpRequestedResource(new OtpChallenge((int) config('otp.ttl_seconds')));
    }
}
