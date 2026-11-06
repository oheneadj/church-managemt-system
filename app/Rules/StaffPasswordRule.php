<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The rules for a staff password (D-054): at least 12 characters, no composition rules, not the person's own email, and at
 * most 72 bytes. The limit is there because bcrypt ignores everything after byte 72, so a longer password would silently be
 * weaker than it looks. Whether it was leaked in a breach is checked afterwards on a worker, never while the person waits.
 */
class StaffPasswordRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * Receives the rest of the request, so the password can be compared with the email.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Fails the field with the first reason the password is not acceptable.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minimum = (int) config('otp.password_min_length');
        $maximum = (int) config('otp.password_max_bytes');

        $reason = match (true) {
            ! is_string($value) => 'The password must be text.',
            mb_strlen($value) < $minimum => "The password must be at least {$minimum} characters.",
            strlen($value) > $maximum => "The password is too long. Use at most {$maximum} characters.",
            strtolower($value) === strtolower((string) ($this->data['email'] ?? '')) => 'The password cannot be your email address.',
            default => null,
        };

        if ($reason !== null) {
            $fail($reason);
        }
    }
}
