<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Contribution;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A gift was recorded (docs/06, docs/11 story 4.7). Fired by the Action inside its transaction; the listener that sends
 * the receipt is queued and waits for the commit, so a gift that is rolled back sends nothing.
 */
class ContributionReceived
{
    use Dispatchable;

    /**
     * Holds the gift that was recorded.
     */
    public function __construct(public readonly Contribution $contribution) {}
}
