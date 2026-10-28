<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PurgeChurchJob;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Finds the churches whose grace period after cancelling is over and queues a purge for each (docs/05). It runs
 * every day from the scheduler. The purging itself happens on a worker.
 */
class PurgeCancelledChurches extends Command
{
    protected $signature = 'churches:purge-cancelled';

    protected $description = 'Queue the purge of every church whose cancellation grace period is over';

    /**
     * Queues one purge job per due church.
     */
    public function handle(ChurchRepositoryInterface $churches): int
    {
        $due = $churches->cancelledBefore(now()->subDays(config('retention.cancelled_church_grace_days')));

        foreach ($due as $church) {
            PurgeChurchJob::dispatch($church->id);
        }

        $this->info("Queued the purge of {$due->count()} church(es).");

        return self::SUCCESS;
    }
}
