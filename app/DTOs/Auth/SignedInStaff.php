<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\Church;
use App\Models\User;

/**
 * The result of a successful sign-in. The token is null for the browser app, which is signed in by cookie instead.
 */
final readonly class SignedInStaff
{
    public function __construct(public User $user, public Church $church, public ?string $token = null) {}
}
