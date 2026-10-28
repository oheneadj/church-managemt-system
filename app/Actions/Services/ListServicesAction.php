<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\ListServicesDTO;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of services, newest first.
 */
final class ListServicesAction
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Lists one page of services.
     *
     * @return LengthAwarePaginator<int, Service>
     */
    public function execute(ListServicesDTO $dto): LengthAwarePaginator
    {
        return $this->services->paginate($dto);
    }
}
