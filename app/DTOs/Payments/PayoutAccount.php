<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;

/**
 * Where a church's money is sent: a mobile money wallet (a number and its network) or a bank account (a number and its bank
 * code, because a name alone cannot be paid, docs/14). Built with `momo()` or `bank()`, so an account is always complete.
 */
final readonly class PayoutAccount
{
    /**
     * Holds the account. Use `momo()` or `bank()`.
     */
    private function __construct(
        public PayoutMethod $method,
        public string $holderName,
        public string $number,
        public ?MomoNetwork $network,
        public ?string $bankCode,
    ) {}

    /**
     * A mobile money wallet: the holder's name, the number in international form, and its network.
     */
    public static function momo(string $holderName, string $number, MomoNetwork $network): self
    {
        return new self(PayoutMethod::Momo, $holderName, $number, $network, null);
    }

    /**
     * A bank account: the holder's name, the account number and the bank's code.
     */
    public static function bank(string $holderName, string $number, string $bankCode): self
    {
        return new self(PayoutMethod::Bank, $holderName, $number, null, $bankCode);
    }
}
