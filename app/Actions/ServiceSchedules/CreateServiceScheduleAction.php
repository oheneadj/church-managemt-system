<?php

declare(strict_types=1);

namespace App\Actions\ServiceSchedules;

use App\DTOs\ServiceSchedules\CreateServiceScheduleDTO;
use App\Models\ServiceSchedule;
use App\Repositories\ServiceScheduleRepositoryInterface;
use App\Services\ScheduledServiceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Sets up a weekly schedule and makes its first weeks of services at once, so the calendar is not empty until the
 * next daily run. The schedule and its services are saved together or not at all.
 */
final class CreateServiceScheduleAction
{
    /**
     * Takes the schedule repository and the generator.
     */
    public function __construct(private readonly ServiceScheduleRepositoryInterface $schedules, private readonly ScheduledServiceGenerator $generator) {}

    /**
     * Creates the schedule as the staff member in the DTO.
     */
    public function execute(CreateServiceScheduleDTO $dto): ServiceSchedule
    {
        return DB::transaction(function () use ($dto): ServiceSchedule {
            $schedule = $this->schedules->create([
                'church_id' => $dto->actor->church_id,
                'name' => $dto->name,
                'type' => $dto->type,
                'weekday' => $dto->weekday,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'is_active' => $dto->isActive,
                'created_by' => $dto->actor->id,
            ]);

            $this->generator->generate($schedule);

            return $schedule;
        });
    }
}
