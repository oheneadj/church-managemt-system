<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Contributions\BatchBankPayoutsAction;
use Illuminate\Console\Command;

/**
 * Puts the waiting payouts of every bank church into a batch once they reach the bank minimum, and queues the batch to be sent (D-072,
 * D-069 step e3). It runs every minute from the scheduler and is safe to run by hand: the payouts are locked while a batch is made, so
 * a payout is in at most one batch.
 */
class BatchBankPayouts extends Command
{
    protected $signature = 'giving:batch-bank-payouts';

    protected $description = 'Put bank churches\' waiting payouts into a batch once they reach the bank minimum';

    /**
     * Makes the batches and says how many.
     */
    public function handle(BatchBankPayoutsAction $batch): int
    {
        $this->info("Made {$batch->execute()} batch(es).");

        return self::SUCCESS;
    }
}
