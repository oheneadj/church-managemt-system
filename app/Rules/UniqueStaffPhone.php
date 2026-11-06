<?php

declare(strict_types=1);

namespace App\Rules;

use App\Repositories\UserRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when another staff member of this church already has the phone number (in international form). A staff member may
 * keep their own number.
 */
class UniqueStaffPhone implements ValidationRule
{
    /**
     * Takes the staff member being changed, if any, so they can keep their own number.
     */
    public function __construct(private readonly ?string $exceptUserId = null) {}

    /**
     * Fails the field when the phone number is taken.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(UserRepositoryInterface::class)->phoneTaken($value, $this->exceptUserId)) {
            $fail('A staff member with this phone number already exists.');
        }
    }
}
