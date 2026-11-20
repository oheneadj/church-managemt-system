<?php

declare(strict_types=1);

namespace App\Actions\ServiceSchedules;

use App\DTOs\ServiceSchedules\ListServiceSchedulesDTO;
use App\Models\ServiceSchedule;
use App\Repositories\ServiceScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the church's weekly schedules.
 */
final class ListServiceSchedulesAction
{
    /**
     * Takes the schedule repository.
     */
    public function __construct(private readonly ServiceScheduleRepositoryInterface $schedules) {}

    /**
     * Lists one page of schedules.
     *
     * @return LengthAwarePaginator<int, ServiceSchedule>
     */
    public function execute(ListServiceSchedulesDTO $dto): LengthAwarePaginator
    {
        return $this->schedules->paginate($dto);
    }
}
