<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\Church;

/**
 * A support request to lift one staff email's sign-in lockout.
 */
final readonly class ResetStaffLockoutDTO
{
    /**
     * Holds the church and the lowercase email.
     */
    public function __construct(public Church $church, public string $email) {}
}
