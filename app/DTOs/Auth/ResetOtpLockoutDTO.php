<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\Church;
use App\ValueObjects\PhoneNumber;

/**
 * A support request to clear one phone number's sign-in lockout.
 */
final readonly class ResetOtpLockoutDTO
{
    public function __construct(public Church $church, public PhoneNumber $phone) {}
}
