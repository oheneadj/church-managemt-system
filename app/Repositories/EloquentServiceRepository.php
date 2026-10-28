<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Services\ListServicesDTO;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
     * Closes every open service whose day ended before the cutoff, in every church. This is a scheduled clean-up, not
     * a church's request, so it deliberately looks across all churches.
     */
    public function closeOpenServicesEndedBefore(CarbonInterface $cutoff): int
    {
        return Service::query()->withoutGlobalScopes()->where('is_open', true)->whereDate('service_date', '<', $cutoff)->update(['is_open' => false]);
    }
}
