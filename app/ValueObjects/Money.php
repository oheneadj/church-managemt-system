<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * An amount of money in Ghana cedis, kept as an exact decimal string with two places (D-017), never as a float.
 * It is the only place an amount is turned into display text, so every screen and API reply shows it the same way.
 */
final readonly class Money implements Stringable
{
    public const CURRENCY = 'GHS';

    /** @var numeric-string */
    public string $amount;

    /**
     * @param  numeric-string|string  $amount  a decimal such as "2400.5" or "2400.50"; negative values are allowed for refunds
     */
    private function __construct(string $amount)
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('An amount must be a decimal number.');
        }

        $this->amount = bcadd($amount, '0', 2);
    }

    /**
     * Builds an amount in cedis from a decimal string, for example the sum the database returns.
     */
    public static function ghs(string $amount): self
    {
        return new self($amount);
    }

    /**
     * The amount with thousands separators and the currency code, for example "GHS 2,400.00".
     */
    public function formatted(): string
    {
        [$whole, $pesewas] = explode('.', $this->amount);
        $negative = str_starts_with($whole, '-');
        $digits = ltrim($whole, '-');

        return self::CURRENCY.' '.($negative ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits).'.'.$pesewas;
    }

    public function __toString(): string
    {
        return $this->amount;
    }
}
