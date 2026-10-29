<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceSchedule;
use App\Repositories\ServiceRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Makes the services a weekly schedule is missing, for the next few weeks (D-050). It looks at the weeks ahead,
 * skips every date the schedule already has a service for (cancelled ones count, so a cancelled date stays
 * cancelled) and makes the rest. It is safe to run any number of times. It opens no transaction: the caller's does,
 * and the unique index on (schedule, date) is the guarantee when two runs overlap.
 */
class ScheduledServiceGenerator
{
    /**
     * Takes the service repository and the service creator.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services, private readonly ServiceCreator $creator) {}

    /**
     * Makes the missing services of this schedule and returns how many were made. A schedule that is off makes none.
     */
    public function generate(ServiceSchedule $schedule): int
    {
        if (! $schedule->is_active) {
            return 0;
        }

        $dates = $this->wantedDates($schedule);
        if ($dates === []) {
            return 0;
        }

        $taken = $this->services->scheduledDates($schedule, CarbonImmutable::parse($dates[0]), CarbonImmutable::parse($dates[array_key_last($dates)]));
        $made = 0;

        foreach (array_diff($dates, $taken) as $date) {
            $made += $this->makeService($schedule, $date) ? 1 : 0;
        }

        return $made;
    }

    /**
     * Makes one service for the date. If another run made it a moment ago (the unique index on schedule and date
     * refuses a second), that is fine: the date has its service, and this returns false.
     */
    private function makeService(ServiceSchedule $schedule, string $date): bool
    {
        try {
            $this->creator->create([
                'church_id' => $schedule->church_id,
                'service_schedule_id' => $schedule->id,
                'name' => $schedule->name,
                'type' => $schedule->type,
                'service_date' => $date,
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'created_by' => $schedule->created_by,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * The dates (YYYY-MM-DD) of the next weeks' services: the first schedule weekday from today in the church's own
     * calendar, then one a week.
     *
     * @return list<string>
     */
    private function wantedDates(ServiceSchedule $schedule): array
    {
        $church = $schedule->church ?? throw new LogicException('A schedule always belongs to a church.');
        $today = CarbonImmutable::now($church->timezone)->startOfDay();
        $first = $today->dayOfWeek === $schedule->weekday->carbonDay() ? $today : $today->next($schedule->weekday->carbonDay());

        $dates = [];

        for ($week = 0; $week < config('attendance.schedule_weeks_ahead'); $week++) {
            $dates[] = $first->addWeeks($week)->toDateString();
        }

        return $dates;
    }
}
