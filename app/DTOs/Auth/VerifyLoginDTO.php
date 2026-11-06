<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\VerifyLoginRequest;
use App\Models\Church;
use Illuminate\Contracts\Session\Session;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Step two of staff sign-in: an emailed code to check. For the browser app it carries the session to sign in; any other
 * client gets a bearer token.
 */
final readonly class VerifyLoginDTO
{
    /**
     * Holds the church, email, code and browser session.
     */
    public function __construct(public Church $church, public string $email, public string $code, public ?Session $browserSession = null) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(VerifyLoginRequest $request): self
    {
        $browserSession = EnsureFrontendRequestsAreStateful::fromFrontend($request) ? $request->session() : null;

        return new self(app('current.church'), $request->email(), $request->code(), $browserSession);
    }
}
