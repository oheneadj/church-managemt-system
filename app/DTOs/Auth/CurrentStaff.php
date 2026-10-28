<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\Church;
use App\Models\User;

/**
 * The signed-in staff member together with their church, as the web app needs them after a refresh.
 */
final readonly class CurrentStaff
{
    public function __construct(public User $user, public Church $church) {}
}
