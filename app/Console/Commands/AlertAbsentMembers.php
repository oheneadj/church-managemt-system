<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendAbsenceAlertsJob;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Queues the automatic absence text for every church that switched it on (story 3.13, D-067). It runs every day from the
 * scheduler and is safe to run by hand: a member already texted for this stretch of absence is not texted again.
 */
class AlertAbsentMembers extends Command
{
    protected $signature = 'attendance:alert-absent';

    protected $description = 'Text members who missed several services in a row, for churches that switched it on';

    /**
     * Queues one job for each church and says how many.
     */
    public function handle(ChurchRepositoryInterface $churches): int
    {
        $queued = 0;

        foreach ($churches->withAbsenceAlerts() as $church) {
            SendAbsenceAlertsJob::dispatch($church->id)->onQueue('notifications');
            $queued++;
        }

        $this->info("Queued the absence text for {$queued} church(es).");

        return self::SUCCESS;
    }
}
