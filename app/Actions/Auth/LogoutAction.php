<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\LogoutDTO;
use App\Models\PersonalAccessToken;
use App\Services\AuthSessionService;

/**
 * Ends the current sign-in: deletes the token for API clients, or closes the cookie session for the browser app.
 */
final class LogoutAction
{
    public function __construct(private readonly AuthSessionService $sessions) {}

    public function execute(LogoutDTO $dto): void
    {
        $token = $dto->user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($dto->session !== null) {
            $this->sessions->endBrowserSession($dto->session);
        }
    }
}
