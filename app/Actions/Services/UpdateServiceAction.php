<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\UpdateServiceDTO;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;

/**
 * Changes a service's name, type, date, times, notes or manual headcount.
 */
final class UpdateServiceAction
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Applies the changed fields.
     */
    public function execute(UpdateServiceDTO $dto): Service
    {
        $service = $dto->service->fill($dto->attributes);

        return $this->services->withAttendanceCount($this->services->save($service));
    }
}
