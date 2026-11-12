<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckDisbursementJob;
use App\Repositories\DisbursementRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Queues a check for every church payout the provider has taken but not finished (D-069 step e4). It runs every minute from the scheduler and
 * is safe to run by hand: a payout is paid once however many times it is asked about. A payout still unfinished after the stale limit is
 * reported in the log, because a person should look into money that has been on its way for an hour.
 */
class CheckStartedPayouts extends Command
{
    protected $signature = 'giving:check-started-payouts';

    protected $description = 'Ask the payment provider about church payouts that are started but not finished';

    /**
     * Queues one check for each started payout and says how many.
     */
    public function handle(DisbursementRepositoryInterface $disbursements): int
    {
        $ids = $disbursements->startedIdsChangedBefore(now()->subSeconds((int) config('giving.payout_check_after_seconds')));

        foreach ($ids as $id) {
            CheckDisbursementJob::dispatch($id)->onQueue('payments');
        }

        $stale = $disbursements->startedIdsChangedBefore(now()->subMinutes((int) config('giving.payout_stale_after_minutes')))->count();

        if ($stale > 0) {
            Log::warning('Church payouts have been unfinished for too long', ['count' => $stale]);
        }

        $this->info("Queued a check for {$ids->count()} payout(s).");

        return self::SUCCESS;
    }
}
