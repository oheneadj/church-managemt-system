<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Services\Platform\PlatformSessionService;
use Illuminate\Contracts\Session\Session;

/**
 * Signs the platform admin out and discards the browser session (D-069).
 */
final class PlatformLogoutAction
{
    /**
     * Takes the session service.
     */
    public function __construct(private readonly PlatformSessionService $sessions) {}

    /**
     * Ends the session.
     */
    public function execute(Session $session): void
    {
        $this->sessions->end($session);
    }
}
