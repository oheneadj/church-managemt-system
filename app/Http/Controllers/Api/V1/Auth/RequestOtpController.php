<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

/**
 * Starts sign-in by texting a code to a staff phone number.
 */
class RequestOtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function __invoke(RequestOtpRequest $request): JsonResponse
    {
        $this->otp->request(app('current.church'), $request->phone());

        return response()->json([
            'message' => 'OTP sent',
            'expires_in' => config('otp.ttl_seconds'),
        ]);
    }
}
