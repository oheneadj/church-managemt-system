<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Church;
use App\ValueObjects\PhoneNumber;
use Illuminate\Contracts\Session\Session;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * A sign-in code to check. For the browser app it carries the session to sign in; any other client gets a token.
 */
final readonly class VerifyOtpDTO
{
    public function __construct(
        public Church $church,
        public PhoneNumber $phone,
        public string $code,
        public ?Session $browserSession = null,
    ) {}

    public static function fromRequest(VerifyOtpRequest $request): self
    {
        $browserSession = EnsureFrontendRequestsAreStateful::fromFrontend($request) ? $request->session() : null;

        return new self(app('current.church'), $request->phone(), $request->code(), $browserSession);
    }
}
