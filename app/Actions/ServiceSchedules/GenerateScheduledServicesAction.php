<?php

declare(strict_types=1);

namespace App\Actions\ServiceSchedules;

use App\Models\ServiceSchedule;
use App\Repositories\ServiceScheduleRepositoryInterface;
use App\Services\ScheduledServiceGenerator;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * The daily job's work: makes the missing services of every active schedule in every church. Each schedule is handled
 * as its own church and in its own transaction, so one failure never undoes the others or stops them.
 */
final class GenerateScheduledServicesAction
{
    /**
     * Takes the schedule repository, the generator and the tenant helper.
     */
    public function __construct(
        private readonly ServiceScheduleRepositoryInterface $schedules,
        private readonly ScheduledServiceGenerator $generator,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Makes the missing services and returns how many were made.
     */
    public function execute(): int
    {
        $made = 0;

        $this->schedules->eachActive(function (ServiceSchedule $schedule) use (&$made): void {
            try {
                $church = $schedule->church ?? throw new LogicException('A schedule always belongs to a church.');

                $made += $this->tenant->run($church, fn (): int => DB::transaction(fn (): int => $this->generator->generate($schedule)));
            } catch (Throwable $failure) {
                // One schedule failing must not stop the others. It is reported, and the next daily run tries it again.
                report($failure);
            }
        });

        return $made;
    }
}
