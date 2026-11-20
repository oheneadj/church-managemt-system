<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckSmsCreditPurchaseJob;
use App\Repositories\SmsCreditPurchaseRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Queues a check for every SMS credit purchase still waiting on the payment provider (D-069 step c). It runs every minute from the
 * scheduler and is safe to run by hand: checking a purchase twice adds its credits once. A purchase still pending after the stale
 * limit is also reported in the log, because a person should look into a payment that never finishes.
 */
class CheckPendingSmsCreditPurchases extends Command
{
    protected $signature = 'credits:check-pending-purchases';

    protected $description = 'Ask the payment provider about SMS credit purchases that are still pending';

    /**
     * Queues one check for each pending purchase and says how many.
     */
    public function handle(SmsCreditPurchaseRepositoryInterface $purchases): int
    {
        $ids = $purchases->pendingIdsStartedBefore(now()->subSeconds((int) config('credits.purchase_check_after_seconds')));

        foreach ($ids as $id) {
            CheckSmsCreditPurchaseJob::dispatch($id)->onQueue('payments');
        }

        $stale = $purchases->pendingIdsStartedBefore(now()->subMinutes((int) config('credits.purchase_stale_after_minutes')))->count();

        if ($stale > 0) {
            Log::warning('SMS credit purchases have been pending for too long', ['count' => $stale]);
        }

        $this->info("Queued a check for {$ids->count()} purchase(s).");

        return self::SUCCESS;
    }
}
