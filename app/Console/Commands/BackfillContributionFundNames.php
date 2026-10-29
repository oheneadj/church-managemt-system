<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Contributions\BackfillContributionFundNamesAction;
use Illuminate\Console\Command;

/**
 * Data migration: fills the fund name snapshot on gifts recorded before the column existed (D-048). It works in
 * batches, only touches gifts that have no name yet, and is safe to run again at any time, also while the app is live.
 */
class BackfillContributionFundNames extends Command
{
    protected $signature = 'contributions:backfill-fund-names';

    protected $description = 'Fill the fund name on gifts that were recorded before it was kept';

    /**
     * Fills the names and says how many gifts it filled.
     */
    public function handle(BackfillContributionFundNamesAction $action): int
    {
        $this->info("Filled the fund name on {$action->execute()} gift(s).");

        return self::SUCCESS;
    }
}
