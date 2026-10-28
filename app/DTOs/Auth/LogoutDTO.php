<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Ending the current sign-in: the staff member and, for the browser app, the session to close.
 */
final readonly class LogoutDTO
{
    public function __construct(public User $user, public ?Session $session = null) {}

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        return new self($user instanceof User ? $user : throw new AuthenticationException, $request->hasSession() ? $request->session() : null);
    }
}
