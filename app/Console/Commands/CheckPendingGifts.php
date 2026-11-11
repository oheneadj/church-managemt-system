<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckContributionPaymentJob;
use App\Repositories\ContributionRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Queues a check for every mobile money gift still waiting on the payment provider (D-069 step d3c). It runs every minute from the
 * scheduler and is safe to run by hand: checking a gift twice counts it once. A gift still pending after the stale limit is also
 * reported in the log, because a person should look into a payment that never finishes.
 */
class CheckPendingGifts extends Command
{
    protected $signature = 'giving:check-pending-gifts';

    protected $description = 'Ask the payment provider about mobile money gifts that are still pending';

    /**
     * Queues one check for each pending gift and says how many.
     */
    public function handle(ContributionRepositoryInterface $contributions): int
    {
        $ids = $contributions->pendingDigitalIdsStartedBefore(now()->subSeconds((int) config('giving.check_after_seconds')));

        foreach ($ids as $id) {
            CheckContributionPaymentJob::dispatch($id)->onQueue('payments');
        }

        $stale = $contributions->pendingDigitalIdsStartedBefore(now()->subMinutes((int) config('giving.stale_after_minutes')))->count();

        if ($stale > 0) {
            Log::warning('Mobile money gifts have been pending for too long', ['count' => $stale]);
        }

        $this->info("Queued a check for {$ids->count()} gift(s).");

        return self::SUCCESS;
    }
}
