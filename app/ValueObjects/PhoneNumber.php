<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Stringable;

/**
 * A phone number in international (E.164) form, e.g. +233244123456.
 * Ghana local numbers like 0244123456 are accepted and converted, so callers never see raw input.
 */
final readonly class PhoneNumber implements Stringable
{
    private function __construct(public string $e164) {}

    /**
     * Returns null when the input cannot be turned into a valid number.
     */
    public static function tryFrom(string $input): ?self
    {
        $digits = preg_replace('/[\s\-().]/', '', $input) ?? '';

        if (preg_match('/^0\d{9}$/', $digits) === 1) {
            $digits = '+233'.substr($digits, 1);
        } elseif (preg_match('/^233\d{9}$/', $digits) === 1) {
            $digits = '+'.$digits;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $digits) === 1 ? new self($digits) : null;
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
