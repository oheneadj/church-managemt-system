<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Contributions\ReconcilePayoutsAction;
use Illuminate\Console\Command;

/**
 * The daily check that every completed mobile money gift has a payout, that no payout is stuck, and that every payout's figures add up
 * (D-018, D-069 step e5). It runs once a day from the scheduler and is safe to run by hand. It writes what it found to the log and ends with
 * a failure code when anything is wrong, so the run itself shows up as failed.
 */
class ReconcilePayouts extends Command
{
    protected $signature = 'giving:reconcile-payouts';

    protected $description = 'Check that collected gifts have been paid out and that the figures add up';

    /**
     * Runs the check and says what it found.
     */
    public function handle(ReconcilePayoutsAction $reconcile): int
    {
        $findings = $reconcile->execute();

        if ($findings === []) {
            $this->info('All payouts reconcile.');

            return self::SUCCESS;
        }

        foreach ($findings as $kind => $ids) {
            $this->error($kind.': '.count($ids));
        }

        return self::FAILURE;
    }
}
