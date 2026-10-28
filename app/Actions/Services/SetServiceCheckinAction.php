<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\SetServiceCheckinDTO;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;

/**
 * Opens or closes a service for check-in. Doing it twice is harmless.
 */
final class SetServiceCheckinAction
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Sets the open flag.
     */
    public function execute(SetServiceCheckinDTO $dto): Service
    {
        $service = $dto->service->forceFill(['is_open' => $dto->isOpen]);

        return $this->services->withAttendanceCount($this->services->save($service));
    }
}
