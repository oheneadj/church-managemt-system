<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Money;
use InvalidArgumentException;

/**
 * The fees on a digital gift (D-018, D-072, docs/04 Platform Fee). All arithmetic is on exact decimal strings (D-017), never
 * floats, and every fee is rounded to two places half-up here and nowhere else (open item C1).
 *
 * - The platform fee is a rate of the gift, kept between a floor and a cap. It is taken from what is paid out to the church.
 * - The collection fee is PayPerlez's charge for taking the money. The giver pays it on top of the gift.
 */
final class GivingFeeCalculator
{
    /**
     * The platform's fee on a gift: the rate of the gift, but never below the floor or above the cap.
     */
    public function platformFee(Money $gift): Money
    {
        $fee = $this->rateOf($gift, $this->setting('giving.platform_fee_rate'));
        $floor = Money::ghs($this->setting('giving.platform_fee_floor'));
        $cap = Money::ghs($this->setting('giving.platform_fee_cap'));

        return match (true) {
            bccomp($fee->amount, $floor->amount, 2) < 0 => $floor,
            bccomp($fee->amount, $cap->amount, 2) > 0 => $cap,
            default => $fee,
        };
    }

    /**
     * What the giver pays PayPerlez on top of the gift for taking the money.
     */
    public function collectionFee(Money $gift): Money
    {
        return $this->rateOf($gift, $this->setting('giving.collection_fee_rate'));
    }

    /**
     * What the giver is asked to pay in all: the gift and the collection fee.
     */
    public function totalToPay(Money $gift): Money
    {
        return Money::ghs(bcadd($gift->amount, $this->collectionFee($gift)->amount, 2));
    }

    /**
     * Whether a gift is within the limits for a digital gift. The limits apply to the gift, not to the total with the fee.
     */
    public function isWithinLimits(Money $gift): bool
    {
        return bccomp($gift->amount, $this->setting('giving.min_digital_gift'), 2) >= 0
            && bccomp($gift->amount, $this->setting('giving.max_digital_gift'), 2) <= 0;
    }

    /**
     * A decimal setting from config/giving.php. A value that is not a number is a mistake in the configuration, so it stops
     * here instead of becoming a wrong fee.
     *
     * @return numeric-string
     */
    private function setting(string $key): string
    {
        $value = (string) config($key);

        return is_numeric($value) ? $value : throw new InvalidArgumentException("The setting {$key} must be a decimal number.");
    }

    /**
     * The rate of an amount, rounded half-up to two places. The sum is worked to six places and half a pesewa is added before
     * the extra places are dropped, which is half-up for amounts that are not negative.
     *
     * @param  numeric-string  $rate
     */
    private function rateOf(Money $amount, string $rate): Money
    {
        return Money::ghs(bcadd(bcmul($amount->amount, $rate, 6), '0.005', 2));
    }
}
