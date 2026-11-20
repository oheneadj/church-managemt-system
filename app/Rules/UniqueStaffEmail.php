<?php

declare(strict_types=1);

namespace App\Rules;

use App\Repositories\UserRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when a staff member of this church already has the email, whatever its capitals. Laravel's own `unique` rule
 * compares exactly, and the database's index does not, so "A@x.com" would pass it and then fail as a server error.
 */
class UniqueStaffEmail implements ValidationRule
{
    /**
     * Fails the field when the email is taken.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(UserRepositoryInterface::class)->emailTaken($value)) {
            $fail('A staff member with this email already exists.');
        }
    }
}
