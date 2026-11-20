<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\ServiceSchedules\ListServiceSchedulesDTO;
use App\Models\ServiceSchedule;
use App\Scopes\ChurchScope;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the service schedule queries.
 */
class EloquentServiceScheduleRepository implements ServiceScheduleRepositoryInterface
{
    /**
     * One page of the church's schedules, by name.
     *
     * @return LengthAwarePaginator<int, ServiceSchedule>
     */
    public function paginate(ListServiceSchedulesDTO $filters): LengthAwarePaginator
    {
        return ServiceSchedule::query()
            ->when($filters->isActive !== null, fn (Builder $q) => $q->where('is_active', $filters->isActive))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * Adds a schedule.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ServiceSchedule
    {
        return ServiceSchedule::query()->create($attributes)->refresh();
    }

    /**
     * Reads the schedule again with its row locked until the transaction ends.
     */
    public function lockForUpdate(ServiceSchedule $schedule): ServiceSchedule
    {
        return ServiceSchedule::query()->lockForUpdate()->whereKey($schedule->getKey())->firstOrFail();
    }

    /**
     * Changes a schedule.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(ServiceSchedule $schedule, array $attributes): ServiceSchedule
    {
        $schedule->update($attributes);

        return $schedule;
    }

    /**
     * Hands every active schedule, in every church, to the callback a few at a time.
     *
     * @param  Closure(ServiceSchedule): void  $callback
     */
    public function eachActive(Closure $callback): void
    {
        ServiceSchedule::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->with('church')
            ->where('is_active', true)
            ->chunkById(config('attendance.schedule_chunk'), function ($schedules) use ($callback): void {
                foreach ($schedules as $schedule) {
                    $callback($schedule);
                }
            });
    }
}
