<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Services\ListServicesDTO;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every query on services lives behind this contract.
 */
interface ServiceRepositoryInterface
{
    /**
     * One page of services, newest first, each with its attendance count.
     *
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginate(ListServicesDTO $filters): LengthAwarePaginator;

    /**
     * Adds a service and reloads its database defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Service;

    /**
     * Saves a changed service.
     */
    public function save(Service $service): Service;

    /**
     * The service with its attendance count loaded.
     */
    public function withAttendanceCount(Service $service): Service;

    /**
     * The service of the current church with this id, if there is one.
     */
    public function find(string $id): ?Service;

    /**
     * Whether any church, including a deleted service, already uses this check-in code. The code is unique across
     * all churches, so this deliberately looks past the church scope.
     */
    public function checkinCodeExists(string $code): bool;

    /**
     * The church's first service on this calendar date: the earliest start time, services with no start time last, then
     * the lowest id. Gifts with no service are reported with it (D-048).
     */
    public function firstOnDate(CarbonInterface $date): ?Service;

    /**
     * The service that uses this check-in code, in any church. The code is unique across churches (D-044), so a
     * member's WhatsApp message with a code names one service. This deliberately looks past the church scope.
     */
    public function findByCheckinCode(string $code): ?Service;

    /**
     * The services open for check-in in these churches, the one opened first at the top (QA-05 TC-05-13). This
     * deliberately looks past the church scope, because it is asked before a church is in context.
     *
     * @param  array<int, string>  $churchIds
     * @return Collection<int, Service>
     */
    public function findOpenInChurches(array $churchIds): Collection;

    /**
     * Closes every open service whose day ended before the cutoff, in every church. Returns how many were closed.
     */
    public function closeOpenServicesEndedBefore(CarbonInterface $cutoff): int;
}
