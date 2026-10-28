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
     * Sets the open flag. The time it was opened is kept only when check-in actually opens, so asking to open an
     * open service again does not move it to the back of the queue for WhatsApp CHECKIN.
     */
    public function execute(SetServiceCheckinDTO $dto): Service
    {
        $service = $dto->service->forceFill(['is_open' => $dto->isOpen]);

        if ($dto->isOpen && ! $dto->service->getOriginal('is_open')) {
            $service->checkin_opened_at = now();
        }

        return $this->services->withAttendanceCount($this->services->save($service));
    }
}
