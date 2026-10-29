<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\CreateServiceDTO;
use App\Models\Service;
use App\Services\ServiceCreator;

/**
 * Creates a service by hand. It starts closed, with a check-in code made by the system.
 */
final class CreateServiceAction
{
    /**
     * Takes the service creator.
     */
    public function __construct(private readonly ServiceCreator $creator) {}

    /**
     * Creates the service, closed, with its code.
     */
    public function execute(CreateServiceDTO $dto): Service
    {
        return $this->creator->create([
            'church_id' => $dto->actor->church_id,
            'name' => $dto->name,
            'type' => $dto->type,
            'service_date' => $dto->serviceDate,
            'starts_at' => $dto->startsAt,
            'ends_at' => $dto->endsAt,
            'notes' => $dto->notes,
            'created_by' => $dto->actor->id,
        ]);
    }
}
