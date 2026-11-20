<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Contributions\ExpireUnpaidContributionAction;
use App\Repositories\ContributionRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Gives up on mobile money gifts nobody approved within the expiry (D-073). It runs every minute from the scheduler and is safe to
 * run by hand. It only expires gifts the check has already had the chance to settle for at least the whole expiry.
 */
class ExpireUnpaidGifts extends Command
{
    protected $signature = 'giving:expire-unpaid-gifts';

    protected $description = 'Mark mobile money gifts that nobody approved in time as expired';

    /**
     * Expires each gift that is still pending past the limit and says how many.
     */
    public function handle(ContributionRepositoryInterface $contributions, ExpireUnpaidContributionAction $expire): int
    {
        $ids = $contributions->pendingDigitalIdsStartedBefore(now()->subMinutes((int) config('giving.unpaid_expiry_minutes')));

        foreach ($ids as $id) {
            $expire->execute($id);
        }

        $this->info("Expired {$ids->count()} gift(s).");

        return self::SUCCESS;
    }
}
