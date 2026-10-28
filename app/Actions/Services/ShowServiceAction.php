<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\ShowServiceDTO;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;

/**
 * Shows one service with its attendance count.
 */
final class ShowServiceAction
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Loads the attendance count of the service.
     */
    public function execute(ShowServiceDTO $dto): Service
    {
        return $this->services->withAttendanceCount($dto->service);
    }
}
