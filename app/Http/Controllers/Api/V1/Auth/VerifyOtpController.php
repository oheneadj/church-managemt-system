<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\ChurchResource;
use App\Http\Resources\UserResource;
use App\Services\AuthSessionService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Completes sign-in. The browser app is signed in with a session cookie and gets no token in the
 * response; any other client gets a bearer token (docs/08-api.md).
 */
class VerifyOtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthSessionService $sessions,
    ) {}

    public function __invoke(VerifyOtpRequest $request): JsonResponse
    {
        $church = app('current.church');
        $user = $this->otp->verify($church, $request->phone(), $request->code());

        $body = [
            'user' => new UserResource($user),
            'church' => new ChurchResource($church),
        ];

        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            $this->sessions->startBrowserSession($user, $request->session());

            return response()->json($body);
        }

        return response()->json([
            'token' => $this->sessions->startTokenSession($user)->plainTextToken,
            'token_type' => 'Bearer',
            ...$body,
        ]);
    }
}
