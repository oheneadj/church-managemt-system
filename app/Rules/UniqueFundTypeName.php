<?php

declare(strict_types=1);

namespace App\Rules;

use App\Repositories\FundTypeRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when this church already has a fund type with the same name, whatever its capitals. Laravel's own `unique`
 * rule compares exactly, so "TITHE" would pass it and then hit the database's case-insensitive index as a server error.
 */
class UniqueFundTypeName implements ValidationRule
{
    /**
     * Takes the fund type being changed, if any, so a fund can keep its own name.
     */
    public function __construct(private readonly ?string $exceptFundTypeId = null) {}

    /**
     * Fails the field when the name is taken.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(FundTypeRepositoryInterface::class)->nameTaken($value, $this->exceptFundTypeId)) {
            $fail('A fund type with this name already exists.');
        }
    }
}
