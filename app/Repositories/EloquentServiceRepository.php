<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Services\ListServicesDTO;
use App\Enums\Weekday;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Scopes\ChurchScope;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent implementation of the service queries.
 */
class EloquentServiceRepository implements ServiceRepositoryInterface
{
    /**
     * One page of services, newest first, each with its attendance count.
     *
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginate(ListServicesDTO $filters): LengthAwarePaginator
    {
        return Service::query()
            ->withCount('attendanceRecords')
            ->when($filters->from !== null, fn (Builder $q) => $q->whereDate('service_date', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $q) => $q->whereDate('service_date', '<=', $filters->to))
            ->when($filters->type !== null, fn (Builder $q) => $q->where('type', $filters->type))
            ->when($filters->isOpen !== null, fn (Builder $q) => $q->where('is_open', $filters->isOpen))
            ->orderByDesc('service_date')
            ->orderByDesc('starts_at')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * Adds a service and reloads its database defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Service
    {
        return Service::query()->create($attributes)->refresh();
    }

    /**
     * Saves a changed service.
     */
    public function save(Service $service): Service
    {
        $service->save();

        return $service;
    }

    /**
     * The service with its attendance count loaded.
     */
    public function withAttendanceCount(Service $service): Service
    {
        return $service->loadCount('attendanceRecords');
    }

    /**
     * The service of the current church with this id, if there is one.
     */
    public function find(string $id): ?Service
    {
        return Service::query()->whereKey($id)->first();
    }

    /**
     * Whether any church, including a deleted service, already uses this check-in code. A deleted service's code is
     * counted too, so a code is never handed out twice in the same table while old rows may still point at it.
     */
    public function checkinCodeExists(string $code): bool
    {
        return Service::query()->withoutGlobalScopes()->withTrashed()->where('checkin_code', $code)->exists();
    }

    /**
     * The church's first service on this calendar date: the earliest start time, services with no start time last, then the lowest id.
     */
    public function firstOnDate(CarbonInterface $date): ?Service
    {
        return Service::query()->whereDate('service_date', $date)->orderBy('starts_at')->orderBy('id')->first();
    }

    /**
     * The dates on which this schedule already has a service, cancelled ones included.
     *
     * @return array<int, string>
     */
    public function scheduledDates(ServiceSchedule $schedule, CarbonInterface $from, CarbonInterface $to): array
    {
        return Service::query()
            ->withTrashed()
            ->where('service_schedule_id', $schedule->id)
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('service_date')
            ->map(fn ($date): string => $date->toDateString())
            ->values()
            ->all();
    }

    /**
     * Changes one field on the schedule's future services that nobody has touched and that still have the old value.
     */
    public function changeUntouchedFuture(ServiceSchedule $schedule, string $field, mixed $old, mixed $new, CarbonInterface $from): int
    {
        return $this->untouchedFuture($schedule, $from)
            ->when($old === null, fn (Builder $q) => $q->whereNull($field), fn (Builder $q) => $q->where($field, $old))
            ->update([$field => $new]);
    }

    /**
     * Moves the schedule's untouched future services to the new weekday.
     */
    public function moveUntouchedFutureToWeekday(ServiceSchedule $schedule, Weekday $weekday, CarbonInterface $from): int
    {
        $moved = 0;

        foreach ($this->untouchedFuture($schedule, $from)->orderBy('service_date')->get() as $service) {
            $date = $service->service_date;
            $target = $date->dayOfWeek === $weekday->carbonDay() ? $date : $date->next($weekday->carbonDay());

            if ($target->isSameDay($date) || in_array($target->toDateString(), $this->scheduledDates($schedule, $target, $target), true)) {
                continue;
            }

            $service->update(['service_date' => $target->toDateString()]);
            $moved++;
        }

        return $moved;
    }

    /**
     * The schedule's services from this day on that are closed, never opened and have nothing recorded at them.
     *
     * @return Builder<Service>
     */
    private function untouchedFuture(ServiceSchedule $schedule, CarbonInterface $from): Builder
    {
        return Service::query()
            ->where('service_schedule_id', $schedule->id)
            ->whereDate('service_date', '>=', $from)
            ->where('is_open', false)
            ->whereNull('checkin_opened_at')
            ->whereDoesntHave('attendanceRecords')
            ->whereDoesntHave('contributions');
    }

    /**
     * Reads the service again with its row locked until the transaction ends.
     */
    public function lockForUpdate(Service $service): Service
    {
        return Service::query()->lockForUpdate()->whereKey($service->getKey())->firstOrFail();
    }

    /**
     * Whether anything is recorded at the service: a check-in or a gift, voided ones included.
     */
    public function hasRecords(Service $service): bool
    {
        return $service->attendanceRecords()->exists() || $service->contributions()->exists();
    }

    /**
     * Removes the service from the calendar. The row is kept (soft-deleted).
     */
    public function delete(Service $service): void
    {
        $service->delete();
    }

    /**
     * The service that uses this check-in code, in any church.
     */
    public function findByCheckinCode(string $code): ?Service
    {
        return Service::query()->withoutGlobalScope(ChurchScope::class)->with('church')->where('checkin_code', $code)->first();
    }

    /**
     * The services open for check-in in these churches, the one opened first at the top.
     *
     * @param  array<int, string>  $churchIds
     * @return Collection<int, Service>
     */
    public function findOpenInChurches(array $churchIds): Collection
    {
        return Service::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->with('church')
            ->whereIn('church_id', $churchIds)
            ->where('is_open', true)
            ->orderBy('checkin_opened_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Closes every open service whose day ended before the cutoff, in every church. This is a scheduled clean-up, not
     * a church's request, so it deliberately looks across all churches.
     */
    public function closeOpenServicesEndedBefore(CarbonInterface $cutoff): int
    {
        return Service::query()->withoutGlobalScopes()->where('is_open', true)->whereDate('service_date', '<', $cutoff)->update(['is_open' => false]);
    }
}
