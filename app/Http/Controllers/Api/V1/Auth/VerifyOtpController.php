<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\ChurchResource;
use App\Http\Resources\UserResource;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

/**
 * Completes sign-in: exchanges a valid code for an access token.
 */
class VerifyOtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthTokenService $tokens,
    ) {}

    public function __invoke(VerifyOtpRequest $request): JsonResponse
    {
        $church = app('current.church');
        $user = $this->otp->verify($church, $request->phone(), $request->code());
        $token = $this->tokens->startSession($user);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'church' => new ChurchResource($church),
        ]);
    }
}
