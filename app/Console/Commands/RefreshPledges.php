<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Pledges\RefreshOverduePledgesAction;
use Illuminate\Console\Command;

/**
 * Moves pledges whose target date has passed to partial or lapsed (D-047). It runs every day from the scheduler and
 * is safe to run by hand at any time.
 */
class RefreshPledges extends Command
{
    protected $signature = 'pledges:refresh';

    protected $description = 'Move pledges past their target date to partial or lapsed';

    /**
     * Refreshes the overdue pledges and says how many it looked at.
     */
    public function handle(RefreshOverduePledgesAction $action): int
    {
        $this->info("Looked at {$action->execute()} overdue pledge(s).");

        return self::SUCCESS;
    }
}
