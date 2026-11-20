<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendDisbursementJob;
use App\Repositories\DisbursementRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Queues a send for every church payout that is due (D-069 step e2): pending ones, failed ones with attempts left, and ones started but
 * never given a provider reference. It runs every minute from the scheduler and is safe to run by hand: sending a payout twice pays it
 * once. It is what retries a payout that had to wait for the provider's balance or that failed.
 */
class SendDuePayouts extends Command
{
    protected $signature = 'giving:send-due-payouts';

    protected $description = 'Send the church payouts that are due';

    /**
     * Queues one send for each due payout and says how many.
     */
    public function handle(DisbursementRepositoryInterface $disbursements): int
    {
        $ids = $disbursements->dueIds(
            now(),
            (int) config('giving.payout_max_attempts'),
            (int) config('giving.payout_retry_after_minutes'),
            (int) config('giving.payout_stuck_after_minutes'),
        );

        foreach ($ids as $id) {
            SendDisbursementJob::dispatch($id)->onQueue('payments');
        }

        $this->info("Queued {$ids->count()} payout(s).");

        return self::SUCCESS;
    }
}
