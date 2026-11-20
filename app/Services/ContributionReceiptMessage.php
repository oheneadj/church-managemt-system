<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\Contribution;
use App\ValueObjects\Money;

/**
 * Writes the receipt text for a gift (docs/04 Giving Receipts): the church, the amount, the fund the gift was given to,
 * the date, and the member's giving in the month of the gift. Words come from config/giving.php, amounts and their
 * formatting from Money, and dates are shown in the church's own timezone.
 */
class ContributionReceiptMessage
{
    /**
     * The text for this gift. `$monthTotal` is the member's giving in the gift's month, this gift included.
     *
     * @param  numeric-string  $monthTotal
     */
    public function write(Church $church, Contribution $contribution, string $monthTotal): string
    {
        $given = $contribution->contributed_at->setTimezone($church->timezone);

        // strtr changes every placeholder in one pass and never looks inside what it put in, so a church or fund whose
        // name happens to contain ":total" cannot have its name rewritten by a later placeholder.
        return strtr((string) config('giving.receipt_template'), [
            ':church' => $church->name,
            ':amount' => Money::ghs($contribution->amount)->formatted(),
            ':fund' => $contribution->fund_name,
            ':date' => $given->format(config('giving.receipt_date_format')),
            ':month' => $given->format(config('giving.receipt_month_format')),
            ':total' => Money::ghs($monthTotal)->formatted(),
        ]);
    }
}
