<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Closes services that were left open after their day ended, in every church, so a forgotten service does not keep
 * accepting check-ins. It runs every hour from the scheduler.
 */
class CloseStaleServices extends Command
{
    protected $signature = 'services:close-stale';

    protected $description = 'Close services that were left open after their day ended';

    /**
     * Closes the stale services and says how many there were.
     */
    public function handle(ServiceRepositoryInterface $services): int
    {
        $closed = $services->closeOpenServicesEndedBefore(now()->subHours(config('attendance.stale_open_hours')));

        $this->info("Closed {$closed} service(s) that were left open.");

        return self::SUCCESS;
    }
}
