<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

use App\Models\Church;
use App\Models\User;

/**
 * The result of starting a church: the new church and its first admin.
 */
final readonly class RegisteredChurch
{
    /**
     * Holds the church and its admin.
     */
    public function __construct(public Church $church, public User $admin) {}
}
