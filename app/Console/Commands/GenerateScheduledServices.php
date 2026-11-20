<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ServiceSchedules\GenerateScheduledServicesAction;
use Illuminate\Console\Command;

/**
 * Makes the next weeks of services for every weekly schedule (D-050). It runs every day from the scheduler and is safe
 * to run by hand at any time: dates that already have a service, cancelled ones included, are left alone.
 */
class GenerateScheduledServices extends Command
{
    protected $signature = 'services:generate-scheduled';

    protected $description = 'Make the coming weeks of services for every weekly schedule';

    /**
     * Makes the missing services and says how many.
     */
    public function handle(GenerateScheduledServicesAction $action): int
    {
        $this->info("Made {$action->execute()} service(s).");

        return self::SUCCESS;
    }
}
