<?php

declare(strict_types=1);

namespace App\Rules;

use App\Repositories\SmsPackageRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when another package already has the same name, whatever its capitals. Laravel's own `unique` rule compares exactly,
 * so "STARTER" would pass it and then hit the database's case-insensitive index as a server error.
 */
class UniqueSmsPackageName implements ValidationRule
{
    /**
     * Takes the package being changed, if any, so a package can keep its own name.
     */
    public function __construct(private readonly ?string $exceptPackageId = null) {}

    /**
     * Fails the field when the name is taken.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(SmsPackageRepositoryInterface::class)->nameTaken($value, $this->exceptPackageId)) {
            $fail('A package with this name already exists.');
        }
    }
}
