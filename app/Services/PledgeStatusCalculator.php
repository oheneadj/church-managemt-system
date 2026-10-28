<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PledgeStatus;
use Carbon\CarbonInterface;

/**
 * Works out a pledge's status from what was promised, what was paid and the dates (D-047). Paid in full is fulfilled,
 * whenever it happens. Otherwise the pledge is active until its target date has passed, and then partial if
 * something was paid or lapsed if nothing was. It reads nothing and saves nothing.
 */
class PledgeStatusCalculator
{
    /**
     * The status for these amounts (decimal strings) on this day.
     *
     * @param  numeric-string  $amountPledged
     * @param  numeric-string  $amountPaid
     */
    public function for(string $amountPledged, string $amountPaid, CarbonInterface $targetDate, CarbonInterface $today): PledgeStatus
    {
        return match (true) {
            bccomp($amountPaid, $amountPledged, 2) >= 0 => PledgeStatus::Fulfilled,
            $today->copy()->startOfDay()->lessThanOrEqualTo($targetDate->copy()->startOfDay()) => PledgeStatus::Active,
            bccomp($amountPaid, '0', 2) > 0 => PledgeStatus::Partial,
            default => PledgeStatus::Lapsed,
        };
    }
}
