<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\ServiceSchedules\ListServiceSchedulesDTO;
use App\Models\ServiceSchedule;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on service schedules lives behind this contract.
 */
interface ServiceScheduleRepositoryInterface
{
    /**
     * One page of the church's schedules, by name.
     *
     * @return LengthAwarePaginator<int, ServiceSchedule>
     */
    public function paginate(ListServiceSchedulesDTO $filters): LengthAwarePaginator;

    /**
     * Adds a schedule.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ServiceSchedule;

    /**
     * Reads the schedule again with its row locked until the transaction ends, so two changes take turns.
     */
    public function lockForUpdate(ServiceSchedule $schedule): ServiceSchedule;

    /**
     * Changes a schedule.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(ServiceSchedule $schedule, array $attributes): ServiceSchedule;

    /**
     * Hands every active schedule, in every church, to the callback a few at a time (the daily job). This deliberately
     * looks past the church scope; the callback must set the schedule's church in context before touching anything else.
     *
     * @param  Closure(ServiceSchedule): void  $callback
     */
    public function eachActive(Closure $callback): void;
}
