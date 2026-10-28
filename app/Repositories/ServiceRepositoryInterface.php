<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Services\ListServicesDTO;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * Whether any church, including a deleted service, already uses this check-in code. The code is unique across
     * all churches, so this deliberately looks past the church scope.
     */
    public function checkinCodeExists(string $code): bool;

    /**
     * Closes every open service whose day ended before the cutoff, in every church. Returns how many were closed.
     */
    public function closeOpenServicesEndedBefore(CarbonInterface $cutoff): int;
}
