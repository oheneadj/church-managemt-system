<?php

declare(strict_types=1);

namespace App\Actions\ServiceSchedules;

use App\DTOs\ServiceSchedules\UpdateServiceScheduleDTO;
use App\Models\ServiceSchedule;
use App\Repositories\ServiceRepositoryInterface;
use App\Repositories\ServiceScheduleRepositoryInterface;
use App\Services\ScheduledServiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Changes a weekly schedule and carries the change to its future services (D-050): only services nobody has touched,
 * and for each changed field only those that still have the schedule's old value, so a service renamed by hand keeps
 * its name. A new weekday moves them. Then the weeks ahead are filled in. All of it is one transaction, with the
 * schedule row locked so two changes take turns.
 */
final class UpdateServiceScheduleAction
{
    /** The schedule fields that are copied to its services, and the service column each one is stored in. */
    private const COPIED_FIELDS = ['name', 'type', 'starts_at', 'ends_at'];

    /**
     * Takes the repositories and the generator.
     */
    public function __construct(
        private readonly ServiceScheduleRepositoryInterface $schedules,
        private readonly ServiceRepositoryInterface $services,
        private readonly ScheduledServiceGenerator $generator,
    ) {}

    /**
     * Changes the schedule and its future services.
     */
    public function execute(UpdateServiceScheduleDTO $dto): ServiceSchedule
    {
        return DB::transaction(function () use ($dto): ServiceSchedule {
            $schedule = $this->schedules->lockForUpdate($dto->schedule);
            $before = $schedule->only([...self::COPIED_FIELDS, 'weekday']);

            $this->schedules->update($schedule, $dto->attributes);
            $this->carryToFutureServices($schedule, $before);
            $this->generator->generate($schedule);

            return $schedule;
        });
    }

    /**
     * Copies each changed field to the untouched future services, then moves them if the weekday changed.
     *
     * @param  array<string, mixed>  $before  the values before the change
     */
    private function carryToFutureServices(ServiceSchedule $schedule, array $before): void
    {
        $church = $schedule->church ?? throw new LogicException('A schedule always belongs to a church.');
        $from = CarbonImmutable::now($church->timezone)->startOfDay();

        foreach (self::COPIED_FIELDS as $field) {
            $old = $before[$field];
            $new = $schedule->{$field};

            if ($old !== $new) {
                $this->services->changeUntouchedFuture($schedule, $field, $old instanceof \BackedEnum ? $old->value : $old, $new instanceof \BackedEnum ? $new->value : $new, $from);
            }
        }

        if ($before['weekday'] !== $schedule->weekday) {
            $this->services->moveUntouchedFutureToWeekday($schedule, $schedule->weekday, $from);
        }
    }
}
